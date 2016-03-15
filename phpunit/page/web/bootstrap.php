<?php
require dirname(__DIR__, 3) . '/vendor/autoload.php';
require __DIR__ . '/lib/Page.php';
require __DIR__ . '/lib/HTML.php';
require __DIR__ . '/lib/Layout.php';
require __DIR__ . '/lib/Config.php';

Config::setFile('tango', __DIR__ . '/config/tango.php');
Config::setFile('html', __DIR__ . '/config/html.php');

define('GIT_ROOT', dirname(__DIR__, 3));

Page::start($_SERVER['SCRIPT_NAME']);
