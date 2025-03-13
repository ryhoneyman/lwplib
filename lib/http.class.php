<?php

namespace LWPLib;

include_once 'base.class.php';

class HTTP extends Base
{
   protected $version = 1.0;

   //===================================================================================================
   // Description: Creates the class object
   // Input: object(debug), Debug object created from debug.class.php
   // Input: array(options), List of options to set in the class
   // Output: null()
   //===================================================================================================
   public function __construct($debug = null, $options = null)
   {
      parent::__construct($debug,$options);
   }

   public function httpMethod()     { return preg_replace('/\W/','',$_SERVER['REQUEST_METHOD']); }
   public function serverProtocol() { return (array_key_exists('SERVER_PROTOCOL',$_SERVER)) ? $_SERVER['SERVER_PROTOCOL'] : null; }
   public function sslProtocol()    { return (array_key_exists('SSL_PROTOCOL',$_SERVER)) ? $_SERVER['SSL_PROTOCOL'] : null; }
   public function sslCipher()      { return (array_key_exists('SSL_CIPHER',$_SERVER)) ? $_SERVER['SSL_CIPHER'] : null; }
   public function requestUri()     { return (array_key_exists('REQUEST_URI',$_SERVER)) ? $_SERVER['REQUEST_URI'] : null; }

   public function userAgent() { return $this->requestHeaders('USER-AGENT'); }

   public function requestHeaders($header = null)
   {
      $headers = array_change_key_case(getallheaders(),CASE_UPPER);

      if (!is_null($header)) { 
        $ucHeader = strtoupper($header);

        return ((array_key_exists($ucHeader,$headers)) ? $headers[$ucHeader] : null); 
      }
 
      return $headers;
   }
}