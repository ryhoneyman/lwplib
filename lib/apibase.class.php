<?php

namespace LWPLib;

include_once 'base.class.php';
include_once 'httpclient.class.php';

class APIBase extends Base
{
   protected $version     = 1.0;
   protected $baseUrl     = null;
   protected $uris        = array();
   protected $httpClient  = null;
   protected $cacheInfo   = null;
   protected $authToken   = null;
   protected $authType    = null;
   protected $authMethods = array();
   public    $httpDebug   = null;
   public    $resultCode  = null;
   public    $errors      = array();

   public function __construct($debug = null, $options = null)
   {
      parent::__construct($debug,$options);

      $this->httpClient = new HttpClient($debug);

      // Default authentication methods
      $this->addAuthMethod(array(
         'auth.header.bearer' => array(
            'header' => array('Authorization' => 'Bearer {{token}}'),
         ),
         'auth.header.ssws' => array(
            'header' => array('Authorization' => 'SSWS {{token}}'),
         ),
         'auth.data.token' => array(
            'data' => array('token' => '{{token}}'),
         )
      ));

      if ($options['baseUrl'])   { $this->baseUrl($options['baseUrl']); }
      if ($options['authToken']) { $this->authToken($options['authToken']); }
      if ($options['authType'])  { $this->authType($options['authType']); }
   }

   public function standardAPIRequest($url, $params = null)
   {
      $this->debug(8,"called");

      $requestResult = $this->makeRequest($url,"auth,json",$params);

      if ($requestResult === false) {
         $this->error($this->httpClient->error());
         return false;
      }

      $response = $this->httpClient->response();
      $status   = $response['status'];
      $error    = $response['error'];

      // status of request errored, set error and return
      if ($status && !preg_match('/^(ok|success|multi)$/i',$status)) {
         $this->error($error);
         return false;
      }

      // status of request was ok, but an error indicating a problem was found, set error and return
      if ($error) {
         $this->error($error);
         return false;
      }

      return $response;
   }

   public function makeRequest($url, $requestType = null, $requestParams = null)
   {    
      $this->debug(8,"called");

      $uriParams = $requestParams['params'] ?: array();
      $data      = $requestParams['data'] ?: array();
      $headers   = $requestParams['headers'] ?: array();
      $options   = $requestParams['options'] ?: null;

      // If we match a preset URI shortcut, replace it here
      if ($this->uris[$url]) { $url = $this->buildUrl($url,$uriParams); } 

      if (!$url) { return false; }

      $headerTypes = array(
         'json' => array('Content-Type' => 'application/json'),
         'form' => array('Content-Type' => 'application/x-www-form-urlencoded'),
      );

      $authMethod = $this->getAuthMethod();

      if (is_array($authMethod['header'])) { $headerTypes['auth'] = $authMethod['header']; }
      if (is_array($authMethod['data']))   { $data = array_merge($data,$authMethod['data']); }

      // Get request types
      $requestTypes = array_flip(explode(',',$requestType));

      // Add in request types for headers to the request
      foreach (array_keys($requestTypes) as $type) {
         if ($headerTypes[$type]) { $headers = array_merge($headers,$headerTypes[$type]); }
      }

      if (array_key_exists('json',$requestTypes)) { $options['decode'] = 'json'; }

      $success = $this->httpClient->send($url,$headers,$data,$options);

      if ($this->httpDebug) { $this->debug(0,json_encode($this->httpClient->responseFull(),JSON_PRETTY_PRINT)); }

      return $success;   
   }

   public function clientError() 
   { 
      return $this->httpClient->error(); 
   }
   
   /**
    * clientResponseValue
    *
    * @param  string $value
    * @return mixed
    */
   public function clientResponseValue($value) 
   { 
      return $this->httpClient->responseValue($value); 
   }
   
   /**
    * clientResponseFull
    *
    * @return mixed
    */
   public function clientResponseFull() 
   { 
      return $this->httpClient->responseFull(); 
   }
   
   /**
    * clientResponse
    *
    * @return mixed
    */
   public function clientResponse() 
   { 
      return $this->httpClient->response(); 
   }

   // return formed url or false on error
   private function buildURL($uriName, $params = null)
   {
      $this->debug(8,"called");

      if (!$this->uris[$uriName]) {
         $this->error("no known uri for name:$uriName");
         return false;
      }

      if (!$this->baseUrl) {
         $this->error("no baseURL set, cannot build url");
         return false;
      }

      $finalUrl    = null;
      $templateUrl = sprintf("%s%s",$this->baseUrl,$this->uris[$uriName]);

      if (!is_null($params)) {
         $replace = array();
         foreach ($params as $key => $value) { $replace['{{'.$key.'}}'] = urlencode($value); }

         $finalUrl = str_replace(array_keys($replace),array_values($replace),$templateUrl);
      }
      else { $finalUrl = $templateUrl; }

      $this->debug(9,"$uriName = $finalUrl");

      return $finalUrl;
   }

   public function loadUris($uriList)
   {
      if (!is_array($uriList))    { $uriList = array($uriList); }
      if (!is_array($this->uris)) { $this->uris = array(); }

      if (empty($uriList)) { return null; }

      $this->uris = array_merge($this->uris,$uriList);

      return true;
   }

   public function baseUrl($baseUrl = null)
   {
      if (!is_null($baseUrl)) { $this->baseUrl = $baseUrl; }

      return $this->baseUrl;
   }

   public function authToken($authToken = null)
   {
      if (!is_null($authToken)) { $this->authToken = $authToken; }

      return $this->authToken;
   }

   public function authType($authType = null)
   {
      $this->debug(8,"called: $authType");

      if (!is_null($authType)) { $this->authType = $authType; }

      return $this->authType;
   }

   public function addAuthMethod($authMethod)
   {
      $this->debug(8,"called");

      if (!is_array($authMethod)) { return null; }

      $this->authMethods = array_merge($this->authMethods,$authMethod);

      return true;
   }

   public function getAuthMethod()
   {
      $this->debug(8,"called");

      // If no or invalid auth type was provided, we'll assume authorization bearer header with no auth in the body
      if (is_null($this->authType) || !$this->authMethods[$this->authType]) { $this->authType('auth.header.bearer'); }

      $this->debug(9,"authType: ".$this->authType);

      $authMethod = $this->authMethods[$this->authType];

      if (!$authMethod) { return null; }

      $authMethod = json_decode(str_replace('{{token}}',$this->authToken,json_encode($authMethod)),true);

      $this->debug(9,"authMethod: ".json_encode($authMethod));

      return $authMethod;
   }

   protected function cacheInfo($name, $data = null, $expires = 900)
   {
      $this->debug(8,"called for $name");

      if (is_null($this->cacheInfo) || !$this->cacheInfo['dir'] || !$this->cacheInfo['files'][$name]['filename']) {
         $this->debug(8,"cache not configured");
         return null;
      }

      $now       = time();
      $fileInfo  = $this->cacheInfo['files'][$name];
      $filename  = sprintf("%s/%s",$this->cacheInfo['dir'],$fileInfo['filename']);
      $expireSec = (!is_null($fileInfo['expireSec'])) ? $fileInfo['expireSec'] : $expires;

      $this->debug(9,"cache file: $filename ($expireSec)");

      if (!is_null($data)) {
         $this->cacheInfo['data'][$name] = $data;

         if ($filename) {
            $this->debug(8,"writing cache data for $name to disk");
            file_put_contents($filename,json_encode(array('expires' => $now + $expireSec, 'data' => $data)));
         }
      }
      else if (!isset($this->cacheInfo['data'][$name])) {
         if ($filename && is_file($filename)) {
            $fileData = json_decode(file_get_contents($filename),true);
            if (!isset($fileData['data']) || ($expireSec && $fileData['expires'] < $now)) {
               $this->debug(8,"invalid or expired data on disk for $name, removing cache file");
               unlink($filename);
               return null;
            }
            $this->debug(8,"using cached data for $name on disk");
            $this->cacheInfo['data'][$name] = $fileData['data'];
         }
      }

      return (isset($this->cacheInfo['data'][$name]) ? $this->cacheInfo['data'][$name] : null);
   }

   public function addCacheInfoFile($name, $fileName, $expires = null)
   {
      if (!$name || !$fileName) { return null; }
      
      $this->cacheInfo['files'][$name] = array('filename' => $fileName, 'expireSec' => $expires);

      return true;
   }

   public function cacheInfoDir($dirName = null)
   {
      if (!is_null($dirName) && is_dir($dirName)) { $this->cacheInfo['dir'] = $dirName; }

      return $this->cacheInfo['dir'];
   }

   // returns decoded JSON or null on failure
   protected function jsonDecode($jsonString)
   {
      $jsonResult = json_decode($jsonString,true);

      return ((!is_null($jsonResult) && json_last_error() == JSON_ERROR_NONE) ? $jsonResult : null);
   }
}
