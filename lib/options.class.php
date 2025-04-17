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
        $this->debug(8,"called, short($shortOpts) long($longOpts)");

        $this->parsedOptions = getopt($shortOpts,explode(',',$longOpts ?: ''));

        $this->debug(9,"Parsed options: ".json_encode($this->parsedOptions));
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
     * Get all parsed options
     *
     * @return array Parsed options array
     */
    public function getAllOptions()
    {
        return $this->parsedOptions;
    }
}

