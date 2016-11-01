<?php

return array (
  'vufind' => 
  array (
    'plugin_managers' => 
    array (
      'ils_driver' => 
      array (
        'factories' => 
        array (
          'voyagerrestful' => 'CARLI\\ILS\\Driver\\Factory::getVoyagerRestful',
          'multibackend' => 'CARLI\\ILS\\Driver\\Factory::getMultiBackend',
          'noils' => 'CARLI\\ILS\\Driver\\Factory::getNoILS',
        ),
      ),
    ),
  ),
);