<h1>Error</h1>
<?php

$fnFilter = function ($s) {
	$s = str_replace(GIT_ROOT . '/', '', $s);
	return htmlspecialchars($s);
};

if (static::$_oThrow) {

	echo '<p>', $fnFilter(static::$_oThrow->getMessage()), '</p>', "\n";
	echo '<p>', $fnFilter(static::$_oThrow->getFile()), '</p>';
}
