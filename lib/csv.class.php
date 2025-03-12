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
// Overview:
//======================================================================================================
/* Example:


*/
//======================================================================================================

include_once 'base.class.php';
include_once 'cipher.class.php';

class CSV extends Base
{
   protected $version  = 1.0;
   protected $errors   = [];
   public    $header   = null;
   private   $bom      = null;
   private   $cipher   = null;

   //===================================================================================================
   // Description: Creates the class object
   // Input: object(debug), Debug object created from debug.class.php
   // Input: array(options), List of options to set in the class
   // Output: null()
   //===================================================================================================
   public function __construct($debug = null, $options = null)
   {
      parent::__construct($debug,$options);
 
      $this->bom = chr(0xEF).chr(0xBB).chr(0xBF);

      $this->cipher = new Cipher($debug);
   }

   public function oldreadFile($filename, $options = null)
   {
      if (!is_file($filename)) { return false; }

      $handle = fopen($filename,'r');

      if (fgets($handle,4) !== $this->bom) { rewind($handle); }

      $csvData = [];
      while(!feof($handle) && ($line = fgetcsv($handle)) !== false) {
          $csvData[] = $line;
      }

      if ($options['hasHeader']) { $this->header = array_shift($csvData); } 

      return $csvData;
   }

   public function readFile($filename, $options = null)
   {
      if (!is_file($filename)) { return false; }

      if ($options['decrypt']) {
         $decOpts   = $options['decrypt'];
         $decKey    = $decOpts['key'];
         $decCipher = ($decOpts['cipher']) ? $decOpts['cipher'] : null;
         $decHash   = ($decOpts['hash']) ? $decOpts['hash'] : null;
         $fileData = $this->cipher->decrypt(file_get_contents($filename),$decKey,$decCipher,$decHash);

         if ($fileData === false) { $this->error('could not decrypt file'); return false; }
  
         $handle = fopen("php://temp", 'r+');
         fwrite($handle,$fileData);
         rewind($handle);

         $fileData = null; // purge the memory of reading in the file
      }
      else { $handle = fopen($filename,'r'); } 

      if (fgets($handle,4) !== $this->bom) { rewind($handle); }

      $separator = $options['separator'] ?: ',';

      $csvData = [];
      while(!feof($handle) && ($line = fgetcsv($handle,null,$separator)) !== false) {
          $csvData[] = $line;
      }

      if ($options['hasHeader']) { $this->header = array_shift($csvData); }

      return $csvData;
   }

   public function writeFile($filename, $input, $options = null)
   {
      $return = file_put_contents($filename,$this->render($input,$options));

      return $return;
   }

   public function render($input, $options = null)
   {
      $return = [];

      $handle = fopen("php://temp", 'r+');

      if ($options['encoding']) {
         if (preg_match('/^utf8-bom$/i',$options['encoding'])) { fprintf($handle,$this->bom); }
      }

      foreach ($input as $line) {
         fputcsv($handle,$line);
      }
      rewind($handle);
      $return = stream_get_contents($handle);
      fclose($handle);

      if ($options['encrypt']) {
         $encOpts   = $options['encrypt'];
         $encKey    = $encOpts['key'];
         $encCipher = ($encOpts['cipher']) ? $encOpts['cipher'] : null;
         $encHash   = ($encOpts['hash']) ? $encOpts['hash'] : null;
         $return = $this->cipher->encrypt($return,$encKey,$encCipher,$encHash);
      }

      return $return;
   }

   public function error($errorMessage = null)
   {
      if (!is_null($errorMessage)) { $this->errors[] = $errorMessage; }
      else {
         $this->debug(8,"returning ".count($this->errors)." error(s)");

         $errors = implode('; ',$this->errors);
         $this->errors = [];

         return $errors;
      }
   }
}