<?php
class Tango {

	static public function init() {

		if (strpos($_SERVER['SCRIPT_NAME'], '..') !== FALSE) {
			die('attack alert');
		}

		spl_autoload_register([__CLASS__, 'tpl']);

		// ini_get('output_buffering');
		$sFile = dirname(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'])
			.'/www/'.ltrim($_SERVER['SCRIPT_NAME'], '/');

		$_SERVER['SCRIPT_FILENAME'] = $sFile;

		return $sFile;
	}

	static public function tpl() {
		echo 'page end';
	}
}
