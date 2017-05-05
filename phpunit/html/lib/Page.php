<?php
class Page extends Tango\Core\Page {

	public static function start(string $sScript, string $sBaseDir = ''): void {
		self::$_sBaseDir = $sBaseDir;
		self::$_sScript = $sScript;
	}
}
