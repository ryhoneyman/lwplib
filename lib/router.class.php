<?php

class Router extends LWPLib\Base
{
   public $controllerName = null;
   public $functionName   = null;
   public $categoryName   = null;
   public $endpointDir    = null;
   public $endpoints      = array();

   public function __construct($debug = null) 
   {
      parent::__construct($debug);
      $this->debug(5,'Router class instantiated');
   }

   public function processRequestPath($request)
   {
      $this->loadEndpoints($request->basePath);

      $this->debug(9,"Loaded ".count($this->endpoints)." endpoints from base ".$request->basePath);

      if (empty($this->endpoints) || !$request->path || !$request->method) { return false; }

      $matchList = array('filter' => array(), 'nofilter' => array());

      // Loop through all the endpoints and determine which ones are match candidates and sort them into filtered and unfiltered groups
      foreach ($this->endpoints as $path => $methods) { 
         foreach ($methods as $method => $info) {
            if ($method != $request->method) { continue; }  // Our request method doesn't match this endpoint method, so we don't need it

            $type = (empty($info['filter'])) ? 'nofilter' : 'filter';

            $matchList[$type][$path] = $info; 
         }
      }

      foreach (array('nofilter','filter') as $matchType) {
         foreach ($matchList[$matchType] as $path => $pathInfo) {
            $matchPath = $this->parsePath($path,$pathInfo['filter']);  // process the path replacing filters with regex patterns

            if ($matchPath === false) { continue; }  // parsing the path failed

            if (substr_count($request->path,'/') == substr_count($matchPath,'/') && preg_match("~^$matchPath$~i",$request->path,$matchFilter)) {
               $this->controllerName = $pathInfo['controller'];
               $this->functionName   = $pathInfo['function'];
               $this->categoryName   = $pathInfo['category'];
               $request->filterData  = $matchFilter;

               return true;
            }
         }
      }

      $this->debug(9,"No match for request path to known endpoints");
       
      return false;
   }

   public function parsePath($path, $pathFilter = null)
   {
      $filterList = array('pattern' => array(), 'replacement' => array());

      if (empty($pathFilter)) { return $path; }

      foreach ($pathFilter as $filterId => $filterData) {
         $constraint = $filterData['constraint'];
         $replace    = null;

         if (preg_match('/^integer$/i',$constraint))     { $replace = "(?<$filterId>\d+)"; }
         else if (preg_match('/^string$/i',$constraint)) { $replace = "(?<$filterId>\S+)"; }

         if (!is_null($replace)) {
            $filterList['pattern'][]     = '/\{'.$filterId.'\}/';
            $filterList['replacement'][] = $replace;
         }
      }

      if (empty($filterList['pattern'])) { return false; }

      $pathParsed = preg_replace($filterList['pattern'],$filterList['replacement'],$path);

      return $pathParsed;
   }

   public function loadEndpoints($basePath = null)
   {
      $return   = false;
      $fileList = array();

      // If we were not given a request path load all json endpoints, otherwise load only the appropriate endpoint file
      if (is_null($basePath)) { $fileList = glob($this->endpointDir.'/*.json'); }
      else { $fileList = array($this->endpointDir.'/'.$basePath.'.json'); }

      foreach ($fileList as $fileName) { 
         $fileResults = $this->getEndpointFile($fileName);
         if (!$fileResults) { continue; }
         $this->endpoints = array_merge($this->endpoints,$fileResults);
         $return = true;
      }

      return $return;
   }

   public function getEndpointFile($fileName)
   {
      $return = false;

      if (!$fileName || !file_exists($fileName)) { return $return; }

      $return = @json_decode(file_get_contents($fileName),true);

      return $return;
   }
}
