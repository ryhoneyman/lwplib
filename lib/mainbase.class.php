<?php

namespace LWPLib;

/**
 * @author    Ryan Honeyman
 * @copyright 2024 Ryan Honeyman
 * @license   MIT
 */

include_once 'base.class.php';
include_once 'debug.class.php';

/**
 * MainBase
 * 
 * <code>
 * options {
 *    'database'       => true|'connect'|'prepare',
 *    'fileDefine'     => null|string,
 *    'dbDefine'       => null|string|array(defineList),
 *    'dbConfigDir'    => null|string,
 *    'autoLoad'       => true|false|string(function),
 *    'sessionStart'   => true|false|array(sessionOptions),
 *    'errorReporting' => true|false|string(error_reporting),
 *    'debugLevel'     => 0-9,
 *    'debugBuffer'    => true|false,
 *    'debugLogDir'    => null|string,
 *    'memoryLimit'    => null|string(ini_mem),
 *    'timezone'       => null|string(tz),
 *    'sendCookies'    => true|false,
 *    'sendHeaders'    => true|false,
 * }
 * </code>
 *
 */
class MainBase extends Base
{
   protected $version      = 1.0;
   public    $debug        = null;
   public    $settings     = array();
   public    $objects      = array();
   public    $classList    = array();
   public    $now          = null;
   public    $startMs      = null;
   public    $pid          = null;
   public    $hostname     = null;
   public    $userName     = null;
   public    $userInfo     = array();
   public    $pageUri      = null;
   public    $autoLoad     = false;
   public    $dbConfigDir  = null;
   public    $dbConfigFile = null;
   public    $cliApp       = null;
   public    $webApp       = null;
   public    $cliOpts      = null;
   
   /**
    * __construct - Creates the class object
    *
    * @param  array|null $options (optional, default null) Class control options
    * @return void
    */
   public function __construct($options = null)
   {
      parent::__construct(null,$options);

      $this->cliApp = (php_sapi_name() == "cli") ? true : false;
      $this->webApp = !$this->cliApp;

      $this->pid      = getmypid();
      $this->hostname = php_uname('n');
      $this->now      = time();
      $this->startMs  = microtime(true);

      if ($this->ifOption('fileDefine') && is_file($options['fileDefine'])) { $this->loadDefinesFromFile($options['fileDefine']); }
      
      // Database control, whether we just prepare or keep a full connection established when we're done initializing
      // Leaving the database disconnected until, and if, required can save resources
      if ($this->ifOption('database') && ($options['database'] === true || preg_match('/^connect$/i',$options['database']))) { $this->settings['connect.database'] = true; }
      else if (preg_match('/^prepare$/i',$options['database'])) { $this->settings['prepare.database'] = true; }

      // Pre-web startup hooks
      if ($this->webApp) { $this->webhookStartup(); }

      // session control must be the very first thing we address, due to header interactions
      if ($this->ifOption('sessionStart') && isset($options['sessionStart'])) { $this->sessionStart($options['sessionStart']); }

      // Logout hook
      if ($this->webApp) { if ($this->webhookLogout()) { exit; } }

      // must turn on error reporting, if requested, immediately to catch errors
      if ($this->ifOption('errorReporting') && isset($options['errorReporting'])) { $this->enableErrorReporting($options['errorReporting']); }

      $this->debug = new Debug();

      if ($this->ifOption('debugLogDir')) { $this->debug->logDir = $options['debugLogDir']; }

      $this->objects['debug'] = $this->debug;

      // Setup debugging options before debugging occurrs.
      if ($this->cliApp) { $this->debugType(DEBUG_CLI); }
      if ($this->ifOption('debugLevel'))  { $this->debugLevel($options['debugLevel']); }
      if ($this->ifOption('debugType'))   { $this->debugType($options['debugType']); }
      if ($this->ifOption('debugBuffer')) { $this->debugBuffer($options['debugBuffer']); }


      $this->settings['defaults'] = array(
         'db.name' => 'default',
      );

      if ($this->ifOption('memoryLimit'))  { $this->setMemoryLimit($options['memoryLimit']); }
      if ($this->ifOption('autoLoad'))     { $this->autoLoad($options['autoLoad']); }
      if ($this->ifOption('dbConfigDir'))  { $this->setDatabaseConfigDir($options['dbConfigDir']); }
      if ($this->ifOption('dbConfigFile')) { $this->setDatabaseConfigFile($options['dbConfigFile']); }

      if ($this->ifOption('cliShortOpts')) { $this->setCliOptions('short',$options['cliShortOpts']); }
      if ($this->ifOption('cliLongOpts'))  { $this->setCliOptions('long',$options['cliLongOpts']); }

      // Class initialization
      $this->initialize($options);

      // Pre-initialize startup hooks
      if ($this->webApp) { 
         $timezone = ($this->ifOption('timezone')) ? $options['timezone'] : null;
         $this->setDefaultTimezone($timezone);
         $this->webhookInit();
      }

      if ($this->ifOption('sendCookies')) { $this->sendCookies($options['sendCookies']); }
      if ($this->ifOption('sendHeaders')) { $this->sendHeaders($options['sendHeaders']); }

      if ($this->webApp) {
         $this->pageUri            = (array_key_exists('SCRIPT_NAME',$_SERVER)) ? $_SERVER['SCRIPT_NAME'] : '';
         $this->userInfo['ipAddr'] = (array_key_exists('HTTP_X_FORWARDED_FOR',$_SERVER)) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : 
                                     (array_key_exists('REMOTE_ADDR',$_SERVER) ? $_SERVER['REMOTE_ADDR'] : null);
      }
      else {
         $this->userName = get_current_user().'@'.$this->hostname;
         $this->userInfo['ipAddr'] = gethostbyname($this->hostname);
      }

      // Final web hooks
      if ($this->webApp) { $this->webhookFinal(); }
   }
   
   /**
    * webhookFinal
    * 
    * @return bool
    */
   public function webhookFinal() { return true; }   
   /**
    * webhookInit
    *
    * @return bool
    */
   public function webhookInit() { return true; }   
   /**
    * webhookLogout
    *
    * @return bool
    */
   public function webhookLogout() { return false; }   
   /**
    * webhookStartup
    *
    * @return bool
    */
   public function webhookStartup() { return true; }
   
   /**
    * getInputVariables - Get GET/POST variables from the HTTP server
    *
    * @return array List of variables
    */
   public function getInputVariables()
   {
      $return = array();

      foreach ($_GET as $key => $value)  { $return[$key] = $value; }
      foreach ($_POST as $key => $value) { $return[$key] = $value; }

      return $return;
   }
   
   /**
    * sendCookies - Send HTTP cookies
    *
    * @param  array|null $cookies (optional, default null) List of cookies name/info to send
    * @return bool
    */
   public function sendCookies($cookies = null)
   {
      if (!is_array($cookies)) { return true; }

      foreach ($cookies as $cookieName => $cookieInfo) {
         setcookie($cookieName,$cookieInfo['value'],$cookieInfo['expires'],$cookieInfo['path'],$cookieInfo['domain'],$cookieInfo['secure'],$cookieInfo['httponly']);
      }

      return true;
   }
   
   /**
    * sendHeaders - Send HTTP headers
    *
    * @param  array|null $headers (optional, default null) List of headers name/value pairs to send
    * @return bool
    */
   public function sendHeaders($headers = null)
   {
      header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
      header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
      header("Cache-Control: no-store, no-cache, must-revalidate");
      header("Cache-Control: post-check=0, pre-check=0", false);
      header("Pragma: no-cache");

      if (!is_array($headers)) { return true; }

      foreach ($headers as $headerName => $headerValue) { header("$headerName: $headerValue"); }

      return true;
   }
   
   /**
    * redirect - Send client redirect and exit
    *
    * @param  string $url Redirect URL
    * @param  array|null $options (optional, default null) Redirect options [not implemented]
    * @return void
    */
   public function redirect($url, $options = null)
   {
      header("Location: $url"); 
      exit;
   }
   
   /**
    * setDefaultTimezone - Set default timezone
    *
    * @param  string|null $tz (optional, default null) Timezone string
    * @return void
    */
   public function setDefaultTimezone($tz = null)
   {
      if (is_null($tz)) { $tz = 'Etc/UTC'; }

      date_default_timezone_set($tz);
   }
   
   /**
    * setMemoryLimit - Set PHP memory limit
    *
    * @param  string|null $limit (optional, default null) PHP memory limit
    * @return bool|null Memory limit set state
    */
   public function setMemoryLimit($limit = null)
   {
      $this->debug(8,"called");

      if (!is_null($limit)) { ini_set('memory_limit',$limit); return true; }

      return null;
   }
   
   /**
    * enableErrorReporting - Set PHP error level
    *
    * @param  int|null $errorLevel (optional, default null) PHP error reporting level (defaults to E_ALL & ~E_NOTICE)
    * @return bool|null Error Reporting state
    */
   public function enableErrorReporting($errorLevel = null)
   {
      if ($errorLevel === false) { return null; }

      if (is_null($errorLevel) || $errorLevel === true) { $errorLevel = E_ALL & ~E_NOTICE; }

      ini_set('display_errors',1);
      ini_set('display_startup_errors',1);
      error_reporting($errorLevel);

      return true;
   }
   
   /**
    * sessionValue - Retrieve (and set) client session value
    *
    * @param  string $name Session key name
    * @param  mixed|null $value (optional, default null) Session value to set
    * @param  bool|null $clear (optional, default null) Whether to clear the session item
    * @return mixed Value of session key
    */
   public function sessionValue($name, $value = null, $clear = false)
   {
      if ($clear) { unset($_SESSION[$name]); return null; }

      if (!is_null($value)) { $_SESSION[$name] = $value; }

      return $_SESSION[$name]; 
   }
   
   /**
    * sessionStart - Start HTTP client session with cookie
    *
    * @param  bool|array|null $options (optional, default null) Session control, true|false or session options, default is no session
    * @return bool|null Session status
    */
   public function sessionStart($options = null)
   {
      $this->debug(8,"called");

      if (is_null($options) || $options === false) { return null; }

      $sessionOptions = ($options === true) ? array() : $options;

      return session_start($sessionOptions);
   }
   
   /**
    * initialize - Initialize all requested main components
    *
    * @param  array $options Associative array of specific components to load
    * @return bool Initialization status
    */
   public function initialize($options)
   {
      $this->debug(8,"called");

      // if define is not in array format, convert it to array
      if ($this->ifOption('dbDefine') && !is_array($options['dbDefine'])) { $options['dbDefine'] = ($options['dbDefine']) ? array($options['dbDefine']) : array(); }

      if ($this->ifSetting('prepare.database') && $this->settings['prepare.database']) { $this->prepareDatabase(); }

      if ($this->ifSetting('connect.database') && $this->settings['connect.database']) {
         if (!$this->connectDatabase()) { $this->debug(0,"Could not establish connection to database"); exit; }
      }

      // load defines.  we have to do this directly because no data providers are loaded yet.
      if ($this->option('dbDefine')) {
         $this->loadDefinesFromDB($this->option('dbDefine'));
      }

      if ($this->option('request')) {
         if (!$this->buildClass('request','LWPLib\Request',null,'request.class.php')) { exit; }
      }

      if ($this->option('input')) {
         if (!$this->buildClass('input','LWPLib\Input',null,'input.class.php')) { exit; }
      }

      if ($this->option('html')) {
         if (!$this->buildClass('html','LWPLib\HTML',null,'html.class.php')) { exit; }
      }

      if ($this->option('adminlte')) {
         if (!$this->buildClass('adminlte','LWPLib\AdminLTE',null,'adminlte.class.php')) { exit; }
      }

      if ($this->option('toastr')) {
         if (!$this->buildClass('toastr','LWPLib\Toastr',null,'toastr.class.php')) { exit; }
      }

      if ($this->cliApp && $this->cliOpts) {
         if (!$this->buildClass('options','LWPLib\Options',null,'options.class.php')) { exit; }
         $this->obj('options')->parseOptions($this->cliOpts['short'] ?? null,$this->cliOpts['long'] ?? null);
      }

      if ($this->option('require')) {
         foreach ($options['require'] as $buildParams) {
            if (count($buildParams) < 4) { $this->debug(0,"Invalid paramters to buildClass: ".json_encode($buildParams)); continue; }

            if (!call_user_func_array(array($this,'buildClass'),$buildParams)) { exit; }
         }
      }

      return true;
   }
   
   /**
    * loadDefinesFromDB - Load global defines from the database
    *
    * @param  array|string|null $list (optional, default null) List of specific defines to load or all if not list provided
    * @return bool Define load status
    */
   public function loadDefinesFromDB($list = null)
   {
      if ($this->connectDatabase() === false) { return false; }

      if ($list && !is_array($list)) { $list = explode(',',$list); }

      // load defines.  we have to do this directly because no data providers are loaded yet.
      $defineList = array_map(function($value) { return "name like '".preg_replace('/[^\w\_\%]/','',$value)."'"; },array_unique($list));

      $query   = "SELECT name,value FROM defines".(($defineList) ? " WHERE (".implode(' OR ',$defineList).")" : '');
      $defines = $this->db()->query($query);

      if (!$defines) { return false; }

      foreach ($defines as $id => $info) {
         if (!defined($info['name'])) { define($info['name'],$info['value']); }
      }

      if (!$this->settings['connect.database']) { $this->disconnectDatabase(); }

      return true;
   }
   
   /**
    * loadDefinesFromFile
    *
    * @param  string $fileName
    * @return bool
    */
   public function loadDefinesFromFile($fileName)
   {
      $this->debug(8,"called");
      
      if (!is_file($fileName)) { return false; }

      $fileDefines = json_decode(@file_get_contents($fileName),true);

      if ($fileDefines && !is_array($fileDefines)) { return false; }

      $this->debug(9,"Loaded ".count($fileDefines)." defines from $fileName");

      foreach ($fileDefines as $defineKey => $defineValue) { define($defineKey,$defineValue); }

      return true;
   }
   
   /**
    * disconnectDatabase - Disconnect a previously connected database
    *
    * @param  string|null $name (optional, default null) Database short name, uses default if not provided
    * @return bool Database disconnected value
    */
   public function disconnectDatabase($name = null)
   {
      $this->debug(8,"called");

      if (is_callable(array($this->db($name),'isConnected')) && !$this->db($name)->isConnected()) { return true; }

      if (is_null($name)) { $name = $this->settings['defaults']['db.name']; }

      if ($this->db($name)) { return $this->db($name)->disconnect(); }

      return false;
   }

   /**
    * connectDatabase - Connect to a database
    *
    * @param  string|null $dbConfigFile (optional, default null) Database configuration file, uses default db.conf if not provided
    * @param  string|null $name (optional, default null) Database short name, uses default if not provided
    * @param  string|null $className Class name
    * @param  string|null $fileName Filename of class
    * @return bool Database prepared value
    */
   public function prepareDatabase($dbConfigFile = null, $name = null, $className = null, $fileName = null)
   {
      $this->debug(8,"called");

      if (is_null($name))         { $name         = $this->settings['defaults']['db.name']; }
      if (is_null($className))    { $className    = 'LWPLib\MySQL'; }
      if (is_null($fileName))     { $fileName     = 'mysql.class.php'; }
      if (is_null($dbConfigFile)) { $dbConfigFile = $this->dbConfigFile ?: 'db.conf'; }

      if (is_a($this->db($name),$className) && $this->db($name)->isConnected()) { return true; }

      // If we were given a relative path, root it to the config directory if set or otherwise current directory
      if (!preg_match('~^/~',$dbConfigFile)) { $dbConfigFile = ($this->dbConfigDir ?: __DIR__).'/'.$dbConfigFile; }

      $dbConnect = json_decode(base64_decode(file_get_contents($dbConfigFile)),true);

      if (!$dbConnect) { return false; }

      $buildResult = $this->buildClass("db.$name",$className,null,$fileName);

      $this->debug(9,"buildResult:".json_encode($buildResult)." for class:$className name:$name");

      if (!$buildResult) { return false; }

      $this->db($name)->prepare($dbConnect['hostname'],$dbConnect['username'],$dbConnect['password'],$dbConnect['database']);

      return true;
   }
   
   /**
    * attachDatabase - Attach to a previously prepared database
    *
    * @param  string|null $name (optional, default null) Database short name, uses default if not provided
    * @return bool Database connected value
    */
   public function attachDatabase($name = null)
   {
      $this->debug(8,"called");

      if (is_null($name)) { $name = $this->settings['defaults']['db.name']; }

      if (!$this->db($name)) { 
         $this->debug(9,"Database $name object does not exist");
         return false; 
      }

      $attachResult = $this->db($name)->attach();

      $this->debug(9,"attachResult:".json_encode($attachResult)." for name:$name");

      return $attachResult;
   }
   
   /**
    * connectDatabase - Connect to a database
    *
    * @param  string|null $dbConfigFile (optional, default null) Database configuration file, uses default db.conf if not provided
    * @param  string|null $name (optional, default null) Database short name, uses default if not provided
    * @param  string|null $className Class name
    * @param  string|null $fileName Filename of class
    * @return bool Database connected value
    */
   public function connectDatabase($dbConfigFile = null, $name = null, $className = null, $fileName = null)
   {
      $this->debug(8,"called");

      if (!$this->prepareDatabase($dbConfigFile,$name,$className,$fileName)) { return false; }

      $connectResult = $this->attachDatabase($name);

      return $connectResult;
   }
   
   /**
    * isDatabaseConnected - Returns whether the named database is connected
    *
    * @param  string|null $name (optional, default null) Database short name, uses default if not provided
    * @return bool Database connected value
    */
   public function isDatabaseConnected($name = null)
   {
      $this->debug(8,"called");

      if (is_null($name)) { $name = $this->settings['defaults']['db.name']; }

      if (!$this->db($name)) { return false; }

      return $this->db($name)->isConnected();
   }
   
   /**
    * buildClass - Instanciate a class
    *
    * @param  string $objName Class object identifier
    * @param  string $className Class name
    * @param  mixed|null $options Class options
    * @param  string|null $fileName Filename of class
    * @return bool Successful class build
    */
   public function buildClass($objName, $className, $options = null, $fileName = null)
   {
      if (!$this->ifClass($className) && !is_null($fileName)) { $this->includeClass($className,$fileName); }

      if (!$this->autoLoad && !$this->ifClass($className)) {
         $this->debug(9,"Could not load class for $className");
         return false;
      }

      $this->objects[$objName] = new $className($this->debug,$options);

      if (!is_a($this->objects[$objName],$className)) {
         $this->debug(9,"Could not build class object for $className");
         return false;
      }

      return true;
   }
   
   /**
    * setDatabaseConfigDir
    *
    * @param  string|null $configDir
    * @return bool
    */
   public function setDatabaseConfigDir($configDir = null)
   {
      if (!is_null($configDir)) { $this->dbConfigDir = $configDir; }

      return true;
   }

   /**
    * setDatabaseConfigFile
    *
    * @param  string|null $configFile
    * @return bool
    */
    public function setDatabaseConfigFile($configFile = null)
    {
       if (!is_null($configFile)) { $this->dbConfigFile = $configFile; }
 
       return true;
    }

   /**
    * setCliOptions
    *
    * @param  string $type CLI options type
    * @param  string $options CLI options
    * @return bool
    */
    public function setCliOptions($type, $options)
    {
       if ($options) { $this->cliOpts[$type] = $options; }
 
       return true;
    } 
   
   /**
    * autoLoad - Class Autoloader 
    *
    * @param  array|string|null $function (optional, default null) Callback function for autoloading, uses 'autoLoaderMain' function by default
    * @return bool
    */
   public function autoLoad($function = null)
   {
      if (is_null($function) || $function === true) { $function = array($this,'autoLoaderMain'); }

      if (!is_callable($function)) { 
         $this->debug(9,'Could not call autoloader function');
         return false; 
      }

      $this->autoLoad = true;

      spl_autoload_register($function);

      $this->debug(9,"Autoload enabled");

      return true;
   }
   
   /**
    * autoLoader - Autoloader callback for load required class
    *
    * @param  string $className Name of class
    * @return bool Successful class load
    */
   public function autoLoaderMain($className)
   {
      $lcName   = strtolower(basename(str_replace("\\",DIRECTORY_SEPARATOR,$className)));
      $fileName = "$lcName.class.php";

      return $this->includeClass($className,$fileName);
   }
   
   /**
    * includeClass - Loads a class if not already loaded
    *
    * @param  string $className Name of class
    * @param  string $fileName Filename for class
    * @return bool Success of loading class
    */
   public function includeClass($className, $fileName)
   {
      $this->debug(8,"called");

      $currentUsed = $this->classUsed();

      if (array_key_exists($className,$currentUsed) && $currentUsed[$className] == $fileName) { 
         $this->debug(9,"Class $className (in $fileName) already loaded.");
         return true; 
      }

      $success = (!@include_once($fileName)) ? false : true;

      $this->debug(8,"Trying to load class $className from file: $fileName (".(($success)?'success':'failure').")");

      if ($success) { $this->classUsed($className,$fileName); }

      return $success;
   }
   
   /**
    * classUsed - Set an entry in the class list once a class is used
    *
    * @param  string|null $className (optional, default null) Class name to register in class list
    * @param  string|null $fileName (optional, default null) Filename of class 
    * @return array Class list
    */
   public function classUsed($className = null, $fileName = null)
   {
      if (!is_null($className)) { $this->classList[$className] = array('fileName' => $fileName); }

      return $this->classList;
   }
   
   /**
    * debugLevel - Gets and sets Debug object level value
    *
    * @param  int|null $level (optional, default null) Debugging level
    * @return int
    */
   public function debugLevel($level = null)
   {
      return $this->debug->level($level);
   }

   /**
    * debugType - Gets and sets Debug object type value (uses defines from Debug)
    *
    * @param  int|null $type (optional, default null) Debugging type
    * @return int
    */
    public function debugType($type = null)
   {
      return $this->debug->type($type);
   }
   
   /**
    * debugBuffer - Gets and sets Debug object buffer value
    *
    * @param  bool|null $state (optional, default null) Debugging buffer state
    * @return bool
    */
   public function debugBuffer($state = null)
   {
      return $this->debug->buffer($state);
   }
   
   /**
    * elapsedRuntime - Returns current elapsed runtime in seconds
    *
    * @return string Elapsed seconds
    */
   public function elapsedRuntime()
   {
      return sprintf("%1.6f",microtime(true) - $this->startMs); 
   }
   
   /**
    * db - Returns database object
    *
    * @param  string|null $name (optional, default null) Database short name, uses default if not provided
    * @return object|null Database object
    */
   public function db($name = null)
   {
      if (is_null($name)) { $name = $this->settings['defaults']['db.name']; }

      return $this->obj("db.$name");
   }
   
   /**
    * getDefined
    *
    * @param  string $key
    * @param  string|null $category
    * @return mixed|null
    */
   public function getDefined($key, $category = null)
   {
      // We don't try to cache this locally because new constants may be set outside of our control
      $definedConstants = get_defined_constants(true);

      if (is_null($category)) { $category = 'user'; }

      return $definedConstants[$category][$key] ?: null;
   }

   /**
    * obj - Returns single object from objects list
    *
    * @param  string $name Object name
    * @return ?object Object
    */
   public function obj($name) { 
      return ($this->ifObject($name)) ? $this->objects[$name] : null; 
   }
   
   /**
     * ifSetting - verify a specific setting was set
     *
     * @param  string $name
     * @return bool
     */
   protected function ifSetting($name)
   {
      return (array_key_exists($name,$this->settings) ? true : false);
   }

   /**
     * ifObject - verify a specific object was set
     *
     * @param  string $name
     * @return bool
     */
   protected function ifObject($name)
   {
      return (array_key_exists($name,$this->objects) ? true : false);
   }

   /**
     * ifClass - verify a specific class was set
     *
     * @param  string $name
     * @return bool
     */
   protected function ifClass($name)
   {
      return (array_key_exists($name,$this->classList) ? true : false);
   }
}
