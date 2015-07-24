<?php
require dirname(__DIR__).'/vendor/autoload.php';

Tango\Core\Config::setFile('db',    __DIR__ . '/db/config.php');
Tango\Core\Config::setFile('mongo', __DIR__ . '/mongo/config.php');

function json($a) {
	return json_encode($a, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
