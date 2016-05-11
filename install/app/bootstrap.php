<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/lib/func.php';

define('ROOT', __DIR__);

Config::setFile('tango', __DIR__ . '/config/tango.php');
Config::setFile('html', __DIR__ . '/config/html.php');

define('INT32_MAX', 2147483647);
define('NOW', $_SERVER['REQUEST_TIME']);
