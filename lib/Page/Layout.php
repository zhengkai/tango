<?php
namespace Tango\Page;

use \Tango\Core\Config;

Config::setFileDefault('layout', dirname(__DIR__).'/Config/layout.php');

class Layout {

	protected static $_sLayout = FALSE; // 不填表示默认

	public static function set($sLayout) {
		self::$_sLayout = $sLayout;
	}

	public static function run($s) {
		if (self::$_sLayout) {
			$fnLayout = Config::get('layout')[self::$_sLayout];
		} else {
			$fnLayout = current(Config::get('layout'));
		}
		$fnLayout($s);
	}
}
