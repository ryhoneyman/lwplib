<?php

namespace LWPLib;

include_once 'base.class.php';

class Format extends Base
{
   protected $version  = 1.0;

   public function __construct($debug = null, $options = null)
   {
      parent::__construct($debug,$options);
   }

   public function formatBytes($bytes)
   {
      $labels = array(
         array('units' => 'B',  'format' => '%d'),
         array('units' => 'KB', 'format' => '%1.1f'),
         array('units' => 'MB', 'format' => '%1.1f'),
         array('units' => 'GB', 'format' => '%1.1f'),
         array('units' => 'TB', 'format' => '%1.1f'),
         array('units' => 'PB', 'format' => '%1.1f'),
         array('units' => 'EB', 'format' => '%1.1f'),
      );
      $exp    = floor(log($bytes) / log(1024));
      $amount = ($bytes / pow(1024,$exp));

      return sprintf($labels[$exp]['format']." %s",$amount,$labels[$exp]['units']);
   }

   public function formatDurationShort($time, $options = null)
   {
      return $this->formatDuration($time,array_merge(array('short' => true),$options ?: array()));
   }

   public function formatDuration($time, $options = null)
   {
      $return = "";
      $pieces = array();
   
      $limiter = ($options['limiter']) ? $options['limiter'] : null;
      $short   = ($options['short']) ? 1 : 0;
   
      $durations = array(
         'year'   => 31536000,
         'week'   => 604800,
         'day'    => 86400,
         'hour'   => 3600,
         'minute' => 60,
         'second' => 1,
      );
   
      if (!$time || $time < 0) { return (($short) ? '0s' : '0 seconds'); }
   
      foreach ($durations as $timeframe => $increment) {
         $test  = $time / $increment;
         $floor = floor($test);
   
         if ($floor > 0) {
            $label = ($short) ? substr($timeframe,0,1) :
                                (" ".(($floor == 1) ? $timeframe : $timeframe."s"));
            $time  = ($test - $floor) * $increment;
            $pieces[] = "{$floor}{$label}";
         }
   
         if (!empty($pieces) && $timeframe == strtolower($limiter)) { break; }
      }
   
      $return = ($short) ? implode('',$pieces) : implode(', ',$pieces);
   
      return $return;
   }
}