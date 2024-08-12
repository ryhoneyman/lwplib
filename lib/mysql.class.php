<?php

namespace LWPLib;
use mysqli_result;

include_once 'base.class.php';

class MySQL extends Base
{
    protected $version      = 1.3;
    public    $connected    = false;
    public    $totalQueries = 0;
    public    $totalRows    = 0;
    public    $lastErrno    = null;
    public    $lastError    = null;

    private $resource;
    private $hostname;
    private $username;
    private $password;
    private $database;

    public function __construct($debug = null)
    {
        parent::__construct($debug);

        mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT ^ MYSQLI_REPORT_INDEX);
    }

    public function connect($hostname, $username, $password, $database, $persistent = false)
    {
        $this->debug(1,"connecting to $hostname($database), user:$username, persistent:$persistent");

        $this->prepare($hostname,$username,$password,$database);

        return $this->attach();
    }

    public function reconnect($issueDisconnect = true)
    {
        if ($issueDisconnect) { $this->disconnect(); }

        return $this->connect($this->hostname, $this->username, $this->password, $this->database);
    }

    public function disconnect()
    {
        $this->connected = false;

        return mysqli_close($this->resource);
    }

    public function attach()
    {
       if ($this->connected) { return true; }

       if (!$this->resource = @mysqli_connect($this->hostname, $this->username, $this->password, $this->database)) {
           $this->debug(1,"unable to establish connection to database");
           return false;
        }

        $this->connected = true;

        $this->debug(1,"connected to database ($this->database)");

        return true;
    }

    public function prepare($hostname = null, $username = null, $password = null, $database = null)
    {
       if (!is_null($hostname)) { $this->hostname = $hostname; } 
       if (!is_null($username)) { $this->username = $username; } 
       if (!is_null($password)) { $this->password = $password; } 
       if (!is_null($database)) { $this->database = $database; } 

       return true;
    }

    public function isConnected()
    {
        return $this->connected;
    }

    public function bindExecute($statement, $types, $data)
    {
        $execResult = false;

        if (!$this->connected) {
            $this->debug(1,"query requested, but database not connected");
            return $execResult;
        }

        $this->debug(9,"connected, bindquery($statement) types($types)");

        $stmt = mysqli_prepare($this->resource,$statement);

        if ($stmt === false) {
            $this->debug(1,"malformed statement in prepare ($statement)");
            return $execResult;
        }

        // If we're not a multidimension array, we'll fabricate one with one element to loop through
        if (count($data) == count($data,1)) { $data = array($data); }

        foreach ($data as $rowId => $rowVars) {
           $varRefs = array();
           foreach (array_keys($rowVars) as $fieldPosition) { $varRefs[$fieldPosition] = &$rowVars[$fieldPosition]; }

           $bindResult = call_user_func_array(array($stmt,'bind_param'),array_merge(array($types),$varRefs));

           if ($bindResult === false) {
              $this->lastErrno = 0;
              $this->lastError = "Could not bind parameters at position $rowId";
              break;
           }

           $execResult = mysqli_stmt_execute($stmt);

           if ($execResult === false) {
              $this->lastErrno = mysqli_stmt_errno($stmt);
              $this->lastError = mysqli_stmt_error($stmt);
              break;
           }
        }

        if ($execResult) {
           $this->queryRows(mysqli_stmt_affected_rows($stmt));
           $this->totalQueries(1);
        }

        mysqli_stmt_close($stmt);

        return $execResult;
    }

    public function execute($statement)
    {
        $result = $this->executeQuery($statement);

        $this->freeResult($result);

        return $result;
    }

    public function bindQuery($statement, $types, $data, $options = null)
    {
       $return = array();

       if (!$this->connected) {
          $this->debug(1,"query requested, but database not connected");
          return false;
       }

       if (!$types && !$data) { return $this->query($statement); }

       if (!is_array($data)) { $data = array($data); }

       $this->debug(9,"bindquery($statement) types($types) data(".json_encode($data).")");

       $stmt = mysqli_prepare($this->resource,$statement);

       if ($stmt === false) {
          $this->debug(1,"malformed statement in prepare ($statement)");
          return false;
       }

       $varRefs = array();
       foreach (array_keys($data) as $fieldPosition) { $varRefs[$fieldPosition] = &$data[$fieldPosition]; }

       $types = ($types) ? array($types) : array('');

       $bindResult = call_user_func_array(array($stmt,'bind_param'),array_merge($types,$varRefs));

       if ($bindResult === false) {
          $this->lastErrno = 0;
          $this->lastError = "Could not bind parameters";
          return false; 
       }

       $execResult = mysqli_stmt_execute($stmt);

       if ($execResult === false) {
          $this->lastErrno = mysqli_stmt_errno($stmt);
          $this->lastError = mysqli_stmt_error($stmt);
          return false;
       }

       $result = mysqli_stmt_get_result($stmt);

       if (!$result) {
           $this->debug(9,"no results, query($statement)");
           return $return;
       }

       return $this->fetchResult($result,$options);
    }

    public function query($param, $options = null)
    {
       $return = array();

       if (!$this->connected) {
          $this->debug(1,"query requested, but database not connected");
          return $return;
       }

       // Support for two types of calls.
       // 1) Called with one argument containing all relevant information, including the query as array
       // 2) Called with two arguments, the query as string and then the relevant options as array
       //==============================================================================================
       if (is_array($param)) {
          if (!key_exists('query', $param)) {
             return $return;
          }

          $query = $param['query'];
          $options = $param;
       }
       else {
          $query = $param;
       }

       $result = $this->executeQuery($query);

       if (!$result) {
           $this->debug(9,"no results");
           return $return;
       }

       return $this->fetchResult($result,$options);
    }

    private function fetchResult($result, $options = null)
    { 
       $return = array();

       $autoindex = isset($options['autoindex']) ? $options['autoindex'] : false;
       $index     = isset($options['index'])     ? $options['index']     : false;
       $single    = isset($options['single'])    ? $options['single']    : false;
       $serial    = isset($options['serialize']) ? $options['serialize'] : false;
       $callback  = isset($options['callback'])  ? $options['callback']  : null;

       $this->debug(9,json_encode(array('autoindex' => $autoindex, 'index' => $index, 'single' => $single, 'serialize' => $serial, 'callback' => $callback),JSON_UNESCAPED_SLASHES));

       $indexCount = 1;
       
       if (!$single) {
          while ($rec = $this->fetchAssoc($result)) {
             if (!$autoindex && !$index) {
                $recKeys = array_keys($rec);
                $index   = array_shift($recKeys);
                $this->debug(9,"no index set, index($index)");
             }

             $id = ($autoindex) ? $indexCount++ : $rec[$index];

             if (!$id) { continue; }

             if (is_callable($callback)) {
                call_user_func_array($callback,array($id,$rec,&$return));
                continue;
             }

             $return[$id] = ($serial) ? serialize($rec) : $rec;
          }
       }
       else {
          $return = $this->fetchAssoc($result);
       }

       $this->freeResult($result);

       if (is_array($return)) { $this->debug(7,"loaded ".count($return)." elements"); }

       return $return;
    }

   public function executeQuery($statement)
   { 
      if (!$this->connected) {
         $this->debug(1,"query requested, but database not connected");
         return false;
      }

      $this->debug(9,"connected, query($statement)");

      $result = mysqli_query($this->resource,$statement);

      if (!empty($result)) {
         $queryrows = (preg_match('/^\s*select/i', $statement)) ? $this->numRows($result) : 0;
         $this->queryRows($queryrows);
         $this->totalQueries(1);
      }

      return $result;
   }

    public function fetchAssoc($result)
    {
        return mysqli_fetch_assoc($result);
    }

    public function fetchObject($result)
    {
        return mysqli_fetch_object($result);
    }

    public function freeResult($result)
    {
        if (!$result instanceof mysqli_result) { return null; }

        mysqli_free_result($result);
        return true;
    }

    public function insertId()
    {
       return mysqli_insert_id($this->resource);
    }

    public function numRows($result)
    {
       return mysqli_num_rows($result);
    }

    public function escapeString($string)
    {
       return mysqli_real_escape_string($this->resource,$string);
    }

    public function autoCommit($value)
    {
       return $this->execute("set autocommit = $value");
    }

    public function startTransaction()
    {
       $this->autoCommit(0);
       $result = $this->execute("start transaction");

       return $result;
    }

    public function endTransaction($commit = 1)
    {
       if ($commit) { $this->commit(); }
       else { $this->rollback(); }

       $result = $this->autoCommit(1);

       return $result;
    }

    public function commit()
    {
       return $this->execute("commit");
    }

    public function rollback()
    {
       return $this->execute("rollback");
    }


    public function queryRows($value = NULL)
    {
       if (is_int($value)) {
          $this->totalRows += $value;
       }
       return $this->totalRows;
    }

    public function affectedRows()
    {
       if (!$this->connected) { return; }

       $rows = mysqli_affected_rows($this->resource);

       return $rows;
    }

    public function totalQueries($value = NULL)
    {
       if (is_int($value)) {
          $this->totalQueries += $value;
       }
       return $this->totalQueries;
    }

    public function setTimezone($offset = null)
    {
       if (!$this->connected) { return; }

       if (!$offset) { return 0; }

       $offset = preg_replace('/[^0-9\:\-\+]/','',$offset);

       $rc = $this->execute("set time_zone = '$offset'");

       $this->debug(7,"setting database timezone to offset: $offset (rc:$rc)");

       return $rc;
    }

    public function lastError()
    {
       $lastErrno = (is_null($this->lastErrno)) ? mysqli_errno($this->resource) : $this->lastErrno;
       $lastError = (is_null($this->lastError)) ? mysqli_error($this->resource) : $this->lastError;

       // Clear the last error variables before returning the errors
       $this->lastErrno = null;
       $this->lastError = null;

       return array($lastErrno,$lastError);
    }
}
