<?php

namespace LWPLib;

include_once 'base.class.php';
include_once 'http.class.php';

class RESTAPI extends Base
{
   protected $version      = 1.0;
   public    $apiKeys      = [];
   public    $keyId        = null;
   public    $responseCode = null;
   public    $headers      = [];
   private   $http         = null;
   private   $loadTime     = null;

   //===================================================================================================
   // Description: Creates the class object
   // Input: object(debug), Debug object created from debug.class.php
   // Input: array(options), List of options to set in the class
   // Output: null()
   //===================================================================================================
   public function __construct($debug = null, $options = null)
   {
      $this->loadTime = hrtime(true);

      parent::__construct($debug,$options);

      if (isset($options['apiKeys'])) { $this->loadApiKeys($options['apiKeys']); }

      $this->http = new HTTP($debug);

      $this->responseCode = 200;
      $this->keyId        = 'UNAUTHORIZED';
   }

   public function router($parameters, $routeList)
   {
      if (!is_array($routeList)) { $this->sendResponse(null,null,500); }

      foreach ($routeList as $route => $routeInfo) {
         if ($this->matchRoute($route)) {
            $routeCallback = $routeInfo['function'];
            $routeMethods  = $routeInfo['method'];

            if (!is_callable($routeCallback))          { $this->sendResponse(null,null,500); }
            if (!$this->validateMethod($routeMethods)) { $this->sendResponse(null,null,405); }

            call_user_func($routeCallback,$parameters);
         }
      }
   }

   public function matchRoute($route)
   {
      return ((preg_match("~^$route~i",$_SERVER['REQUEST_URI'])) ? true : false);
   }

   public function sendResponse($output = null, $headers = null, $responseCode = null, $outputEncoding = null)
   {
      // send response code header
      $this->sendHeaders($this->formatResponseCode($responseCode));

      // send any additional headers, if they are set
      $headers = (is_null($headers)) ? $this->headers : $headers;

      if ($headers) { $this->sendHeaders($headers); }

      // send response output
      print $this->formatOutput($output,$outputEncoding);

      // no further output should occur after response sent
      exit;
   }

   public function formatResponseCode($code = null, $message = null)
   {
      $protocol     = ($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
      $responseCode = (is_null($code)) ? $this->responseCode : $code;

      return "$protocol $responseCode".((!is_null($message)) ? " $message": '');
   }

   public function sendHeaders($headers = null)
   {
      if (is_null($headers)) { return null; }

      if (!is_array($headers)) { $headers = array($headers); }

      foreach ($headers as $header) { header($header); }
   }

   public function formatOutput($output = null, $encoding = null)
   {
      $return = '';

      if (is_null($encoding)) { $encoding = 'json'; }

      if (preg_match('/^json$/i',$encoding)) { $return = json_encode($output); }
      else if (!is_null($output)) { $return = $output; }

      return $return;
   }

   public function standardMulti($info = null, $responseCode = null)
   {
      if (is_null($responseCode)) { $responseCode = 207; }

      return $this->standardStatus('multi',$info,$responseCode);
   }

   public function standardOk($info = null, $responseCode = null)
   {
      return $this->standardStatus('ok',$info,$responseCode);
   }

   public function standardStatus($status, $info = null, $responseCode = null)
   {
      $return = array('status' => $status);

      // Log before appending the info, because we don't want the output in the log - just the status
      $this->logResponse(json_encode($return));

      if (is_array($info)) { $return = array_merge($return,$info); }

      if (!is_null($responseCode)) { $this->responseCode = $responseCode; }

      return $return;
   }

   public function standardError($error = null, $responseCode = null)
   {
      $return = array('status' => 'error', 'error' => $error);

      $this->logResponse(json_encode($return));

      if (!is_null($responseCode)) { $this->responseCode = $responseCode; }

      return $return;
   }

   public function standardErrorUnauthorized() 
   {
      return $this->standardError('Unauthorized',401);
   }

   public function standardErrorUnsupportedMethod()
   {
      return $this->standardError('Unsupported method',405);
   }

   public function validate($keyList = null, $methodList = null)
   {
      if (!$keyList || empty($keyList)) { 
         $this->sendResponse($this->standardError('Service Unavailable',503));
      }

      if ($this->validateAuthentication($this->allowKeys($keyList)) === false) {
         $this->sendResponse($this->standardErrorUnauthorized());
      }

      if ($this->validateMethod($methodList) === false) {
         $this->sendResponse($this->standardErrorUnsupportedMethod());
      }

      return true;
   }

   public function validateMethod($methodList = null)
   {
      if (!is_array($methodList)) { return true; }

      $method = strtoupper($this->httpMethod());

      return ((preg_grep("~$method~i",$methodList)) ? true : false);
   }

   public function validateAuthentication($keyList = null) 
   {
      if (is_null($keyList)) { return null; }

      $findKey = $this->getAuthentication(in_array('SESSION',array_values($keyList)));
      $apiAuth = ($keyList[$findKey]) ? $findKey : null;
      $valid   = (is_null($apiAuth)) ? false : true;

      if ($valid !== false) {
         // If our key was based on session, append username to value
         if (preg_match('/^session$/i',$keyList[$apiAuth])) { $this->keyId = $keyList[$apiAuth].'/'.$apiAuth; }
         // If our key came from an API key, prepend key identifier
         else { $this->keyId = 'KEY/'.$keyList[$apiAuth]; }
      } 

      $this->logDebugAuth(sprintf("validateAuthentication - keyLength:%d, valid:%d, keyId:%s",strlen($findKey),$valid,$this->keyId));

      $this->logRequest();

      return $valid;
   }

   public function getAuthentication($useSession = false)
   {
      $key = null;
   
      if ($_SERVER['HTTP_X_APIKEY']) {
         $key = $_SERVER['HTTP_X_APIKEY'];
      }
      else if ($_SERVER['HTTP_AUTHORIZATION']) {
         // Strip Bearer or Token off the authorization header
         $key = preg_replace('/^\S+\s+/','',$_SERVER['HTTP_AUTHORIZATION']);
      }
      else {
         $headers = array_change_key_case(apache_request_headers(),CASE_LOWER);

         // Log all headers except the authorization header
         $this->logDebugHeaders(json_encode(array_diff_key($headers,array('authorization' => true))));

         if (in_array('authorization',array_keys($headers))) { $key = preg_replace('/^\S+\s+/','',$headers['authorization']); }
      }

      // If we are allowing user authenticated sessions and we didn't find an API key, set the key to the session username
      if ($useSession && is_null($key) && $_SESSION['username']) { $key = $_SESSION['username']; }

      $this->logDebugAuth(sprintf("getAuthentication - useSession:%d, keyProvided:%d",$useSession,!is_null($key)));

      return $key;
   }

   public function matchMethod($method)
   {
      return (preg_match("~^$method$~i",$this->httpMethod())) ? true : false;
   }

   public function httpMethod()     { return $this->http->httpMethod(); }
   public function serverProtocol() { return $this->http->serverProtocol(); }
   public function sslProtocol()    { return $this->http->sslProtocol(); }
   public function sslCipher()      { return $this->http->sslCipher(); }
   public function userAgent()      { return $this->requestHeaders('USER-AGENT'); }

   public function requestHeaders($header = null) { return $this->http->requestHeaders($header); }

   public function allowKeys($lookup)
   {
      if (!is_array($lookup)) { $lookup = explode(',',$lookup); }

      return array_filter(array_flip(array_intersect_key($this->apiKeys,array_flip($lookup))));
   }

   private function loadApiKeys($apiKeysJson)
   {
      $this->apiKeys = json_decode($apiKeysJson,true);
   }

   private function logRequest()
   {
      $this->logUser(APP_LOGDIR.'/api/api.request.log');
   }

   private function logResponse($message)
   {
      $this->logUser(APP_LOGDIR.'/api/api.response.log',$message);
   }

   private function logDebugAuth($message)
   {
      $this->logBase(APP_LOGDIR.'/api/api.auth.debug.log',$message);
   }

   private function logDebugHeaders($message)
   {
      $this->logBase(APP_LOGDIR.'/api/api.headers.debug.log',$message);
   }

   private function logUser($file, $message = null)
   {
      $fullMessage = sprintf("%d (%s) %s",$_SERVER['CONTENT_LENGTH'],$this->keyId,$message);

      $this->logBase($file,$fullMessage);
   }

   private function logBase($file, $message = null)
   {
      $totalTime = hrtime(true) - $this->loadTime;
      $request   = sprintf("[%s] (%s) %15s %6s %s %s\n",date('Y-m-d H:i:s'),sprintf("%03.4fs",$totalTime/1e+9),
                           $_SERVER['REMOTE_ADDR'],$this->httpMethod(),$_SERVER['REQUEST_URI'],$message);

      @file_put_contents($file,$request,FILE_APPEND|LOCK_EX);
   }
}