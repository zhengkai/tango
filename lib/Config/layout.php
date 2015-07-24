<?php
use Tango\Page\HTML;

return [
	'default' => function($s) {
		require HTML::getTpl('head');
		echo "\n";
		require HTML::getTpl('nav');
		echo "\n";

		echo $s;

		echo "\n\n";

		require HTML::getTpl('foot');
	},
];
