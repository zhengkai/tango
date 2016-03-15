<?php
class HTML extends Tango\Page\HTML {

	public static function debugReset() {
		self::$_sTpl = '';
		self::$_sTplType = '';
	}
}
