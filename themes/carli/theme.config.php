<?php
return array(
    'extends' => 'bootprint3',
    'js' => array(
        'googlefonts.js',
        'carli.js',
        'check_requestable.js',
        'image-map.js',
        'vendor/jquery-ui.min.js',
    ),
   'css' => array(
        'carli.css',
        'vendor/jquery-ui.css',
     ),
    'favicon' => 'carli-favicon.ico',
    'helpers' => array(
        'factories' => array(
            'recorddataformatter' => 'CARLI\View\Helper\Root\RecordDataFormatterFactory',
        ),
    ),
);

