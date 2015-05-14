#! /usr/bin/env php
<?php
require __DIR__ . '/common.inc.php';

Tango\Core\Config::setFile('tango', __DIR__ . '/config_tango.php');

echo 'start pid=' . ($iPID = getmypid()) . "\n";

if (Tango\Core\Util::flock(3, 'lockname_test.lock')) {
	echo 'free' . "\n";
	sleep(5);
} else {
	echo 'locked' . "\n";
}
echo 'end   pid=' . $iPID . "\n";
