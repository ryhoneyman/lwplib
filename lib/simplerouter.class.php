<?php

namespace LWPLib;

include_once 'base.class.php';
include_once 'http.class.php';

/**
 * SimpleRouter
 */
class SimpleRouter extends Base
{
    protected $version      = 1.0;
    public    $responseCode = null;
    public    $headers      = [];
    public    $http         = null;
    
    /**
     * __construct
     *
     * @param  Debug|null $debug Debug object
     * @param  array|null $options Class control options
     * @return void
     */
    public function __construct($debug = null, $options = null)
    {
        parent::__construct($debug,$options);

        $this->http = new HTTP($debug);

        $this->responseCode = 200;
    }
    
    /**
     * process
     *
     * @param  array $parameters Parameters passed to the route
     * @param  array $routeList List of routes to process
     * @return void
     */
    public function process($parameters, $routeList)
    {
        $this->debug(8,"called");

        if (!is_array($routeList)) { $this->sendResponse(null,null,500); }

        $routeMatch = false;

        foreach ($routeList as $route => $routeInfo) {
            if ($this->matchRoute($route)) {
                $routeMatch    = true;
                $routeCallback = $routeInfo['function'];
                $routeMethods  = $routeInfo['method'];

                if (!is_callable($routeCallback))          { $this->sendResponse(null,null,500); }
                if (!$this->validateMethod($routeMethods)) { $this->sendResponse(null,null,405); }

                call_user_func($routeCallback,$parameters);
            }
        }

        if (!$routeMatch) { $this->sendResponse("No matching route found",null,404); }
    }
    
    /**
     * matchRoute
     *
     * @param  string $route Route to match
     * @return bool True if the route matches the request URI
     */
    public function matchRoute($route)
    {
        return ((preg_match("~^$route~i",$this->http->requestUri())) ? true : false);
    }
    
    /**
     * sendResponse
     *
     * @param  string|null $output Output to send
     * @param  array|string|null $headers Headers to send
     * @param  int|null $responseCode Response code to send
     * @param  string|null $outputEncoding Encoding for the output
     * @return void
     */
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
    
    /**
     * formatResponseCode
     *
     * @param  int|null $code Response code to send
     * @param  string|null $message Message to send with the response code
     * @return string Formatted response code
     */
    public function formatResponseCode($code = null, $message = null)
    {
        $protocol     = ($this->http->serverProtocol()) ? $this->http->serverProtocol() : 'HTTP/1.0';
        $responseCode = (is_null($code)) ? $this->responseCode : $code;

        return "$protocol $responseCode".((!is_null($message)) ? " $message": '');
    }
    
    /**
     * sendHeaders
     *
     * @param  array|string|null $headers Headers to send
     * @return void
     */
    public function sendHeaders($headers = null)
    {
        if (is_null($headers)) { return null; }

        if (!is_array($headers)) { $headers = array($headers); }

        foreach ($headers as $header) { header($header); }
    }
    
    /**
     * formatOutput
     *
     * @param  string|null $output Output to format
     * @param  string|null $encoding Encoding for the output
     * @return string Formatted output
     */
    public function formatOutput($output = null, $encoding = null)
    {
        $return = '';

        if (is_null($encoding)) { $encoding = 'json'; }

        if (preg_match('/^json$/i',$encoding)) { $return = json_encode($output); }
        else if (!is_null($output)) { $return = $output; }

        return $return;
    }
    
    /**
     * validateMethod
     *
     * @param  array|null $methodList List of valid methods
     * @return bool True if the method is valid
     */
    public function validateMethod($methodList = null)
    {
        if (!is_array($methodList)) { return true; }

        $method = strtoupper($this->http->httpMethod());

        return ((preg_grep("~$method~i",$methodList)) ? true : false);
    }
}