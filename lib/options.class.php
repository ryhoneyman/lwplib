<?php

namespace LWPLib;

include_once 'base.class.php';

/**
 * Options
 */
class Options extends Base
{
    protected $version       = 1.0;
    protected $parsedOptions = []; // To store parsed options

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
    }

    /**
     * Parse options using getopt
     *
     * @param string|null $shortOpts Short options string
     * @param string|null $longOpts Long options string, to be split into an array
     * @return void
     */
    public function parseOptions($shortOpts = null, $longOpts = null)
    {
        $this->debug(8,"called");

        $this->parsedOptions = getopt($shortOpts,explode(',',$longOpts ?? ''));
    }

    /**
     * Get a parsed option value
     *
     * @param string $key Option key
     * @param mixed $default Default value if the key is not found
     * @return mixed Option value or default
     */
    public function getOption($key, $default = null)
    {
        return $this->parsedOptions[$key] ?? $default;
    }

    /**
     * Return whether an option is set
     *
     * @param string $key Option key
     * @return bool True if the option is set, false otherwise
     */
    public function isOptionSet($key)
    {
        return ((isset($this->parsedOptions[$key])) ? true : false);
    }

    /**
     * Return ternary values if an option is set
     *
     * @param string $key Option key
     * @param mixed $isSet Value to return if the option is set
     * @param mixed $isNotSet Value to return if the option is not set
     * @return mixed Value based on whether the option is set or not
     */
    public function ifOptionSet($key, $isSet = true, $isNotSet = false)
    {
        return (($this->isOptionSet($key)) ? $isSet : $isNotSet);
    }

    /**
     * Get all parsed options
     *
     * @return array Parsed options array
     */
    public function getAllOptions()
    {
        return $this->parsedOptions;
    }
}

