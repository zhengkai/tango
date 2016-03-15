#! /usr/bin/env php
<?php
if (PHP_SAPI !== 'cli') {
	echo 'run in cli', "\n";
	exit;
}

$s = FALSE;
if (is_array($argv ?? FALSE) && count($argv) > 1) {
	$s = array_pop($argv);
}

if (!is_string($s)) {
	echo 'no test name', "\n";
	exit;
}

if (!preg_match('#^[a-z_\.]+$#', $s)) {
	echo 'invalid test name: ', $s, "\n";
	exit;
}

$_SERVER['SCRIPT_NAME'] = '/' . $s . '.php';

require __DIR__ . '/bootstrap.php';
