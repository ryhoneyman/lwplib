<?php

namespace LWPLib;

//    Copyright 2009,2010 - Ryan Honeyman
//
//    This program is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 3 of the License, or
//    (at your option) any later version.
//
//    This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with this program.  If not, see <http://www.gnu.org/licenses/>
//

//======================================================================================================
// Overview: Cipher library for PHP
//======================================================================================================
/* Example:

*/
//======================================================================================================

define("CIPHER_AES256",256);

class Cipher extends Base
{
   protected $version = 1.0;
   protected $cipher  = CIPHER_AES256;
   protected $key     = 'PHPSESSID';

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

   public function encode($data, $cipher = null, $key = null)
   {
      if (is_null($cipher)) { $cipher = $this->cipher; }
      if (is_null($key))    { $key    = $this->key; }

      switch ($cipher) {
         case CIPHER_AES256: $encdata = $this->aes256_crypt($data,$key,'e'); break;
         default:            $encdata = "";
      }

      return $encdata;
   }

   public function decode($encdata, $cipher = null, $key = null)
   {
      if (is_null($cipher)) { $cipher = $this->cipher; }
      if (is_null($key))    { $key    = $this->key; }

      switch ($cipher) {
         case CIPHER_AES256: $data = $this->aes256_crypt($encdata,$key,'d'); break;
         default:            $data = "";
      }

      return $data;
   }

   public function aes256_crypt($data, $key, $action = 'e') {
      $output = false;
      $iv     = 'secret_iv';
      $method = "AES-256-CBC";
      $skey   = hash('sha256',$key );
      $siv    = substr(hash('sha256',$iv),0,16);

      if ($action == 'e') {
          $output = base64_encode(openssl_encrypt($data,$method,$skey,0,$siv));
      }
      else if ($action == 'd') {
          $output = openssl_decrypt(base64_decode($data),$method,$skey,0,$siv);
      }

      return $output;
   }
}
