<?php

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
// Overview: Produce debugging assertions for code troubleshooting
//======================================================================================================
/* Example:

   // Explictly calling debug with it's methods.

   $debug = new Debug();

   $debug->level(9);
   $debug->type(DEBUG_HTML);
   $debug->trace(9,'Maximum debug level, will display in HTML format');

   // Calling debug with it's defaults (level 0, type COMMENT)

   $debug = new Debug();

   $debug->trace(0,'Minimum debug level, will display as comments in HTML');

*/
//======================================================================================================

define("DEBUG_HTML",1);
define("DEBUG_COMMENT",2);
define("DEBUG_CLI",3);

include_once 'base.class.php';

class Debug extends Base
{
   protected $version = 1.0;
   protected $level   = 0;
   protected $type    = null;
   protected $buffer  = false;
   public    $log     = array();
   private   $lastMs  = null;

   //===================================================================================================
   // Description: Creates the class object
   // Input: int(level), Default level for debug filtering
   // Input: int(type), Display debug type: DEBUG_HTML/COMMENT/CLI
   // Input: array(options), Class control options
   // Output: null()
   //===================================================================================================
   public function __construct($level = 0, $type = DEBUG_COMMENT, $options = null)
   {
      $this->type($type);
      $this->level($level);

      if (isset($options['buffer'])) { $this->buffer($options['buffer']); } 

      $lastMs = microtime(true);
   }

   //===================================================================================================
   // Description: Performs a traceback to assert the current call
   // Input: int(level), Level for this assertion
   // Input: string(mesg), Debug message to send
   // Input: int(caller), Caller index in the backtrace array
   // Output: null()
   //===================================================================================================
   public function trace($level, $mesg, $caller = 1)
   {
      $current = $this->level();

      if ($current < $level) { return; }

      $mesg  = preg_replace('/\s*$/','',$mesg);
      $trace = debug_backtrace();

      if (!$trace[$caller]['line']) { $caller = 0; }

      $date  = date("Ymd-H:i:s");
      $func  = $trace[$caller]['function'];
      $line  = $trace[$caller]['line'];
      $file  = preg_replace('/^.*\//','',$trace[$caller]['file']);
      $class = ($trace[$caller]['class']) ? $trace[$caller]['class'] : "Main";

      $output = sprintf("%s[%s] %s (%s, %s, %s:%s) %s%s\n",
                        ($this->comment()) ? "<!-- " : "",$date,$level,$class,$func,$file,$line,$mesg,
                        ($this->comment()) ? " -->" : (($this->htmlize()) ? "<br>" : ""));

      if ($this->buffer()) { $this->log[] = $output; }
      else { print $output; }
   }

   //===================================================================================================
   // Description: Gets (and sets) current debug buffer mode
   // Input: bool(value), Requested buffer mode, if supplied
   // Output: bool(store), Current buffer setting
   //===================================================================================================
   public function buffer($value = null)
   {
      if (isset($value)) { $this->buffer = ($value) ? true : false; }
      return (($this->buffer) ? true : false);
   }

   //===================================================================================================
   // Description: Gets (and sets) current debug level
   // Input: int(level), Global assertion level to set
   // Output: int(level), Global assertion level
   //===================================================================================================
   public function level($level = null)
   {
      if (isset($level)) { $this->level = $level; }
      return $this->level;
   }

   //===================================================================================================
   // Description: Gets (and sets) current debug type
   // Input: int(type), Global assertion type to set
   // Output: int(type), Global assertion type
   //===================================================================================================
   public function type($type = null)
   {
      if (isset($type)) { $this->type = $type; }
      return $this->type;
   }

   //===================================================================================================
   // Description: Detect if HTML debugging is enabled
   // Input: null()
   // Output: boolean(value), True if HTML debugging is enabled
   //===================================================================================================
   public function htmlize() { return ($this->type() == DEBUG_HTML); }


   //===================================================================================================
   // Description: Detect if COMMENT debugging is enabled
   // Input: null()
   // Output: boolean(value), True if COMMENT debugging is enabled
   //===================================================================================================
   public function comment() { return ($this->type() == DEBUG_COMMENT); }

   //===================================================================================================
   // Description: Returns current buffered log, then flushes it
   // Output: string(log), Current log buffer
   //===================================================================================================
   public function getLog()
   {
      $data = $this->log;
      $this->log = array();
      return $data;
   }

   public function writeFile($fileName, $mesg, $append = null)
   {
      $flags = (is_null($append) || $append === true) ? FILE_APPEND : 0;

      return @file_put_contents($fileName,sprintf("[%s] %s\n",date('Y-m-d H:i:s'),$mesg),$flags);
   }

   public function traceDuration($mesg)
   {
      if ($this->level < 9) { return false; }

      $nowMs = microtime(true);

      if (!$this->lastMs) { $this->lastMs = $nowMs; }

      $this->trace(9,sprintf("%s: %1.6f secs",$mesg,($nowMs - $lastMs)));

      $this->lastMs = $nowMs;

      return true;
   }
}

?>
