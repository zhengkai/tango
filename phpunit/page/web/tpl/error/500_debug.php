<?php
Page::setLayout('Base');
?>
<h1>Error</h1>
<?php

$fnFilter = function ($s) {
	$s = str_replace(GIT_ROOT . '/', '', $s);
	return htmlspecialchars($s);
};

if ($oThrow = Page::getThrow()) {
	echo '<p>', $fnFilter($oThrow->getMessage()), '</p>', "\n";
	echo '<p>', $fnFilter($oThrow->getFile()), '</p>';
}
