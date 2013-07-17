<?php
namespace Tango\Core;

Config::setFileDefault('layout', dirname(dirname(__DIR__)).'/config/layout.php');

class Layout {

	static protected $_sLayout = FALSE; // 不填表示默认

	static public function set($sLayout) {
		self::$_sLayout = $sLayout;
	}

	static public function run($s) {
		if (self::$_sLayout) {
			$fnLayout = Config::get('layout')[self::$_sLayout];
		} else {
			$fnLayout = current(Config::get('layout'));
		}
		$fnLayout($s);
	}
}
