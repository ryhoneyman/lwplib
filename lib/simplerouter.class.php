<?php

namespace LWPLib;

include_once 'base.class.php';
include_once 'http.class.php';

class SimpleRouter extends Base
{
    protected $version      = 1.0;
    public    $responseCode = null;
    public    $headers      = [];
    public    $http         = null;

    //===================================================================================================
    // Description: Creates the class object
    // Input: object(debug), Debug object created from debug.class.php
    // Input: array(options), List of options to set in the class
    // Output: null()
    //===================================================================================================
    public function __construct($debug = null, $options = null)
    {
        parent::__construct($debug,$options);

        $this->http = new HTTP($debug);

        $this->responseCode = 200;
    }

    public function process($parameters, $routeList)
    {
        $this->debug(8,"called");

        if (!is_array($routeList)) { $this->sendResponse(null,null,500); }

        foreach ($routeList as $route => $routeInfo) {
            if ($this->matchRoute($route)) {
                $routeCallback = $routeInfo['function'];
                $routeMethods  = $routeInfo['method'];

                if (!is_callable($routeCallback))          { $this->sendResponse(null,null,500); }
                if (!$this->validateMethod($routeMethods)) { $this->sendResponse(null,null,405); }

                call_user_func($routeCallback,$parameters);
            }
        }
    }

    public function matchRoute($route)
    {
        return ((preg_match("~^$route~i",$this->http->requestUri())) ? true : false);
    }

    public function sendResponse($output = null, $headers = null, $responseCode = null, $outputEncoding = null)
    {
        // send response code header
        $this->sendHeaders($this->formatResponseCode($responseCode));

        // send any additional headers, if they are set
        $headers = (is_null($headers)) ? $this->headers : $headers;

        if ($headers) { $this->sendHeaders($headers); }

        // send response output
        print $this->formatOutput($output,$outputEncoding);

        // no further output should occur after response sent
        exit;
    }

    public function formatResponseCode($code = null, $message = null)
    {
        $protocol     = ($this->http->serverProtocol()) ? $this->http->serverProtocol() : 'HTTP/1.0';
        $responseCode = (is_null($code)) ? $this->responseCode : $code;

        return "$protocol $responseCode".((!is_null($message)) ? " $message": '');
    }

    public function sendHeaders($headers = null)
    {
        if (is_null($headers)) { return null; }

        if (!is_array($headers)) { $headers = array($headers); }

        foreach ($headers as $header) { header($header); }
    }

    public function formatOutput($output = null, $encoding = null)
    {
        $return = '';

        if (is_null($encoding)) { $encoding = 'json'; }

        if (preg_match('/^json$/i',$encoding)) { $return = json_encode($output); }
        else if (!is_null($output)) { $return = $output; }

        return $return;
    }

    public function validateMethod($methodList = null)
    {
        if (!is_array($methodList)) { return true; }

        $method = strtoupper($this->http->httpMethod());

        return ((preg_grep("~$method~i",$methodList)) ? true : false);
    }
}