<?php

//    Copyright 2023 - Ryan Honeyman

include_once 'base.class.php';

class ConstantsBase extends Base
{
   public $list = null;

   public function __construct($debug = null, $options = null)
   {
      parent::__construct($debug,$options);

      $configFile = $options['configFile'] ?: APP_CONFIGDIR.'/constants.json';

      $this->list = json_decode(@file_get_contents($configFile),true);

   }

   public function fetch($section) { return $this->list[$section]; }

   public function list() { return $this->list; }
}
?>
