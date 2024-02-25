<?php

class Response extends Base
{
   public $protocol        = 'HTTP/1.0';
   public $statusCode      = 500;
   public $statusMessage   = 'Internal Server Error';
   public $contentType     = '';
   public $contentBody     = '';
   public $contentLength   = 0;
   public $customHeaders   = array();

   public function __construct($debug = null) 
   {
      parent::__construct($debug);
      $this->debug(5,'Response class instantiated');
   }

   public function setProtocol($protocol)
   {
      $this->debug(7,'Protocol set to '.$protocol);
      $this->protocol = $protocol;
   }

   public function setStatus($statusCode, $statusMessage = '')
   {
      $this->debug(7,'Status set to code ['.$statusCode.'] '.$statusMessage);
      $this->statusCode    = $statusCode;
      $this->statusMessage = $statusMessage;
   }

   public function setContentType($contentType)
   {
      $this->debug(7,'Content-Type set to '.$contentType);
      $this->contentType = $contentType;
   }

   public function setContentBody($contentBody)
   {
      $contentLength = strlen($contentBody);
      $this->debug(7,'Set content body, '.$contentLength.' bytes');
      $this->contentBody   = $contentBody;
      $this->contentLength = $contentLength;
   }

   public function addCustomHeader($header)
   {
      $this->customHeaders[] = $header;
   }

   public function sendHeader()
   {
      $phpSapiName = substr(php_sapi_name(),0,3);

      // Main header with status code must be delivered first
      if (preg_match('/^(cgi|fpm)$/i',$phpSapiName)) {
         $this->debug(9,'CGI/FPM SAPI detected, header status code['.$this->statusCode.']');
         @header('Status: '.$this->statusCode.' '.$this->statusMessage);
      } 
      else {
         $this->debug(9,'Header status code ['.$this->statusCode.'] '.$this->statusMessage);
         @header($this->protocol.' '.$this->statusCode.' '.$this->statusMessage);
      }

      // Content type header is the next important piece after status
      if ($this->contentType) {
         $this->debug(9,'Header content type ['.$this->contentType.']');
         @header('Content-Type: '.$this->contentType);
      }

      // Now we can display our other headers
      foreach ($this->customHeaders as $header) {
         @header($header);
      }
   }

   public function sendBody()
   {
      echo $this->contentBody;
   }
}
?>
