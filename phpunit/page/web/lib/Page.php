<?php
class Page extends Tango\Core\Page {

	protected static function _fallbackTplDebug(): string {
		return dirname(__DIR__) . '/tpl/error/500_debug.php';
	}

	public static function initLayout() {

		if (!self::$_oLayout) {
			self::$_oLayout = new Layout();
		}
		return self::$_oLayout;
	}
}
