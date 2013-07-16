<?php
namespace Tango\Core;

Config::setFileDefault('layout', dirname(dirname(__DIR__)).'/config/layout.php');

class Layout {

	static protected $_sLayout = 'default';

	static public function run($s) {
		$fnLayout = Config::get('layout')[self::$_sLayout];
		$fnLayout($s);
	}
}
