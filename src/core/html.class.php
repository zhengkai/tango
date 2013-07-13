<?php
namespace Tango\Core;

class HTML {

	static public function run($T) {
		echo '<pre>';
		echo htmlspecialchars(print_r($T, TRUE));
		echo '</pre>';
	}
}
