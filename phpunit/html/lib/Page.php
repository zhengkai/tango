<?php
class Page extends Tango\Core\Page {

	public static function start(string $sURI): void {
		self::$_sBaseDir = '/basedir';
		self::$_sURI = $sURI;
	}
}
