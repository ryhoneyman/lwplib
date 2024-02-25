<?php

class Request extends Base
{
   public $serverVars;    // Server environment variables loaded by the webserver
   public $pathInfo;      // Pathinfo from request
   public $pathList;      // Array of path information split on forwardslash
   public $apiVersion;    // The first part of the path is which version of the api is being called
   public $path;          // Full path string without the api version
   public $basePath;      // Base path of endpoint
   public $protocol;      // HTTP protocol, HTTP/1.x
   public $method;        // HTTP method, GET/POST/PUT/DELETE
   public $parameters;    // Passed information on the GET line or POST body
   public $filterData;    // path filter data processed by router
   public $format;        // Format of returned data, used by view
   public $auth;          // User authorization string
   public $keyId;         // User key ID from client id/secret
   public $token;         // User request token for authentication

   public function __construct($debug = null) 
   {
      parent::__construct($debug);
      $this->debug(5,'Request class instantiated');

      // use the default server variables, these can be overridden via method after constructor.
      $this->setServerVars($_SERVER);
   }

   public function setServerVars($vars)
   {
      $this->debug(7,'method called');

      $this->serverVars = $vars;

      // if PATH_INFO isn't set because this is a rewrite redirection, set it now
      $this->serverVars['PATH_INFO'] = $this->serverVars['REDIRECT_URL'];
 
      $this->getProtocol();
      $this->getMethod();
      $this->getPath();
      $this->getParameters();
   }

   public function getProtocol()
   {
      $this->debug(7,'method called');

      $this->protocol = isset($this->serverVars['SERVER_PROTOCOL']) ? $this->serverVars['SERVER_PROTOCOL'] : 'HTTP/1.0';
   }

   public function getMethod()
   {
      $this->debug(7,'method called');

      $this->method = strtoupper($this->serverVars['REQUEST_METHOD']);
   }

   public function getPath()
   {
      $this->debug(7,'method called');

      $this->pathInfo   = $this->serverVars['PATH_INFO'];
      $this->pathList   = explode('/',trim($this->pathInfo,'/'));
      $this->apiVersion = array_shift($this->pathList);
      $this->path       = '/'.implode('/',$this->pathList);
      $this->basePath   = $this->pathList[0];
   }

   public function getParameters() 
   {
      $this->debug(7,'method called');

      $parameters  = array();
      $postVars    = array();
      $contentType = null;
      $body        = '';

      // acquire parameters passed on the URI
      if (isset($this->serverVars['QUERY_STRING'])) {
         parse_str($this->serverVars['QUERY_STRING'],$parameters);
      }

      // extract content type from headers, if passed.  we'll use this to determine return data format
      if (isset($this->serverVars['CONTENT_TYPE'])) {
         $contentType = $this->serverVars['CONTENT_TYPE'];
         $this->debug(9,'CONTENT_TYPE detected: '.$contentType);
      }

      // get user authorization information/token if supplied
      if (isset($this->serverVars['HTTP_AUTHORIZATION'])) {
         $this->auth = $this->serverVars['HTTP_AUTHORIZATION'];

         if (preg_match('/^token\s+token=(.*)/i',$this->serverVars['HTTP_AUTHORIZATION'],$match)) {
            $this->token = trim($match[1],'"');
            $this->debug(9,'HTTP_AUTHORIZATION token detected: '.$this->token);
         } 
         else if (preg_match('/^bearer\s+(.*)/i',$this->serverVars['HTTP_AUTHORIZATION'],$match)) {
            $this->token = $match[1];
            $this->debug(9,'HTTP_AUTHORIZATION bearer detected: '.$this->token);
         }
      }

      // PUT/POST methods may have body information we need to process
      if (preg_match('/^(put|post)$/i',$this->method)) { 
         $this->debug(9,strtoupper($this->method).' detected, getting body...');
         $body = file_get_contents("php://input");
      }

      // process the request 
      switch($contentType) {
         case "application/json":
            $this->debug(9,'JSON format requested, decode json body');
            $this->format = "json";
            $bodyParams   = json_decode($body);

            if ($bodyParams) {
               foreach ($bodyParams as $bpName => $bpValue) {
                  $parameters[$bpName] = $bpValue;
               }
            }
            break;

         case "application/x-www-form-urlencoded":
            $this->debug(9,'HTML format requested, decode form body');
            $this->format = "html";
            parse_str($body,$postVars);
            foreach ($postVars as $pvName => $pvValue) {
               $parameters[$pvName] = $pvValue;
            }
            break;

         default:
            $this->debug(9,'NO format requested, default to HTML');
            $this->format = "html";
            // other formats
            break;
      }

      // rhoneyman I don't like this, need to consider removing
      // Allow the user to override the content type for return data with a format parameter
      if (isset($parameters['format'])) { 
         $this->format = $parameters['format']; 
         $this->debug(9,'Format override requested, using '.$this->format);
      }

      $this->parameters = $parameters;
   }
}
?>
