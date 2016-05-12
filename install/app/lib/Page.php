<?php
class Page extends Tango\Core\Page {

	protected static function _debugPage(string $sTpl = ''): string {
		return parent::_debugPage(dirname(__DIR__) . '/tpl/error/500_debug.php');
	}

	protected static function _notfoundPage(string $sTpl = ''): string {
		trigger_error('not found');
		return parent::_notfoundPage(dirname(__DIR__) . '/tpl/error/404.php');
	}

	public static function initLayout() {

		if (!static::$_oLayout) {
			static::$_oLayout = new Layout();
		}
		return static::$_oLayout;
	}
}
