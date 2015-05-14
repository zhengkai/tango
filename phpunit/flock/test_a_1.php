#! /usr/bin/env php
<?php
require __DIR__ . '/common.inc.php';

echo 'start pid=' . ($iPID = getmypid()) . "\n";

if ($s = Tango\Core\Util::flock()) {
	echo 'free' . "\n";
	sleep(5);
} else {
	echo 'locked' . "\n";
}
echo 'end   pid=' . $iPID . "\n";
