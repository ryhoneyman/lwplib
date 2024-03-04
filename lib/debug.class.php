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
   public    $logDir  = null;
   private   $lastMs  = null;

   /**
    * __construct - Creates the class object
    *
    * @param  int|null $level (optional, default 0) Default level for debug filtering: 0(none) - 9(full)
    * @param  int|null $type (optional, default DEBUG_COMMENT) Display debug type: DEBUG_HTML/DEBUG_COMMENT/DEBUG_CLI
    * @param  array|null $options (optional, default null) Class control options
    * @return void
    */
   public function __construct($level = 0, $type = DEBUG_COMMENT, $options = null)
   {
      $this->type($type);
      $this->level($level);

      if (isset($options['buffer'])) { $this->buffer($options['buffer']); } 

      $this->lastMs = microtime(true);
   }

   /**
    * trace - Performs a traceback to assert the current call
    *
    * @param  int $level Debug level for this assertion
    * @param  string $mesg Debug message to send
    * @param  int|null $caller (optional, default 1) Caller index in the backtrace array
    * @return void
    */
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

   /**
    * buffer - Gets (and sets) current debug buffer mode
    *
    * @param  bool|null $value (optional, default null) Requested buffer mode, if supplied
    * @return bool Current buffer setting
    */
   public function buffer($value = null)
   {
      if (isset($value)) { $this->buffer = ($value) ? true : false; }
      return (($this->buffer) ? true : false);
   }

   /**
    * level - Gets (and sets) current debug level
    *
    * @param  int|null $level (optional, default null) Global assertion level to set
    * @return int Global assertion level
    */
   public function level($level = null)
   {
      if (isset($level)) { $this->level = $level; }
      return $this->level;
   }

   /**
    * type - Gets (and sets) current debug type (DEBUG_HTML/DEBUG_COMMENT/DEBUG_CLI)
    *
    * @param  int|null $type (optional, default null) Global assertion type to set
    * @return int Global assertion type
    */
   public function type($type = null)
   {
      if (isset($type)) { $this->type = $type; }
      return $this->type;
   }

   /**
    * htmlize - Detect if HTML debugging is enabled
    *
    * @return bool True if HTML debugging is enabled
    */
   public function htmlize() { 
      return ($this->type() == DEBUG_HTML); 
   }

   /**
    * comment - Detect if COMMENT debugging is enabled
    *
    * @return bool True if COMMENT debugging is enabled
    */
   public function comment() { 
      return ($this->type() == DEBUG_COMMENT); 
   }

   /**
    * getLog - Returns current buffered log, then flushes the entries
    *
    * @return array Current log buffer
    */
   public function getLog()
   {
      $data = $this->log;
      $this->log = array();
      return $data;
   }
   
   /**
    * writeFile - Writes entry to logfile
    *
    * @param  string $fileName File to write
    * @param  string $mesg Message to write to file
    * @param  bool|null $append (optional, default null) Append to file, off by default
    * @return int|false Bytes written or false for error
    */
   public function writeFile($fileName, $mesg, $append = null)
   {
      $flags = (is_null($append) || $append === true) ? FILE_APPEND : 0;

      if (!preg_match('~^/~',$fileName)) { 
         if (!is_dir($this->logDir)) { return false; } 
         $fileName = $this->logDir.'/'.$fileName; 
      }

      return @file_put_contents($fileName,sprintf("[%s] %s\n",date('Y-m-d H:i:s'),$mesg),$flags);
   }
   
   /**
    * traceDuration - Print a message and runtime duration in seconds
    *
    * @param  string $mesg Message to ouput
    * @param  float|null $lastMs (optional, default null) Last microsecond timestamp, now by default
    * @return bool Message written
    */
   public function traceDuration($mesg, $lastMs = null)
   {
      if ($this->level < 9) { return false; }

      $nowMs = microtime(true);

      if (!is_null($lastMs)) { $this->lastMs = $lastMs; }

      if (!$this->lastMs) { $this->lastMs = $nowMs; }

      $this->trace(9,sprintf("%s: %1.6f secs",$mesg,($nowMs - $this->lastMs)));

      $this->lastMs = $nowMs;

      return true;
   }
}
