<?php
namespace Tango\Core;

Config::setFileDefault('debug', dirname(__DIR__).'/Config/debug.php');

class Debug {

	static $_bEnable;
	static $_sDebugPath;

	public static function _init() {

		if (self::$_bEnable !== NULL) {
			return self::$_bEnable;
		}

		$aConfig = Config::get('debug');

		if (!$aConfig['enable']) {
			return self::$_bEnable = FALSE;
		}

		$sPath = rtrim(trim($aConfig['path']), '/');
		if (!$sPath) {
			return self::$_bEnable = FALSE;
		}

		if (file_exists($sPath)) {
			if (!is_dir($sPath) || !is_writable($sPath)) {
				return self::$_bEnable = FALSE;
			}
		} else {
			$sDir = dirname($sPath);
			if (!is_writable($sDir)) {
				return self::$_bEnable = FALSE;
			}
			$iMask= umask(0);
			$bCreate = (bool)@mkdir($sPath, 0755);
			umask($iMask);
			if (!$bCreate) {
				return self::$_bEnable = FALSE;
			}
		}

		self::$_sDebugPath = $sPath;

		return self::$_bEnable = TRUE;
	}

	public static function add($sType, $sMessage = NULL, $bHead = FALSE) {
		if ($sMessage === NULL) {
			return FALSE;
		}
		self::_file($sType, $sMessage, $bHead, FILE_APPEND | LOCK_EX);
	}

	public static function dump($sType, $sMessage = NULL, $bHead = FALSE) {
		if ($sMessage === NULL) {
			return FALSE;
		}
		self::_file($sType, $sMessage, $bHead, LOCK_EX);
	}

	public static function _file($sType, $sMessage, $bHead, $iFlag) {

		if (!self::_init()) {
			return FALSE;
		}

		if (!is_string($sType) && !preg_match('#[0-9a-z\_]{1, 30}#', $sType)) {
			return FALSE;
		}

		$aConfig = Config::get('debug');

		$sFile = self::$_sDebugPath.'/'.$sType;
		if (disk_free_space(self::$_sDebugPath) < $aConfig['disk_free_space']) {
			return FALSE;
		}

		if (file_exists($sFile) && (filesize($sFile) > $aConfig['max_size'] || !is_writable($sFile))) {
			return FALSE;
		}

		if (!is_string($sMessage)) {
			$sMessage = json_encode($sMessage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}

		if ($bHead) {
			$fTimeCost = sprintf('%.06f', microtime(TRUE) - $_SERVER['REQUEST_TIME_FLOAT']);
			$sHead= $_SERVER['REQUEST_URI']."\n"
				.date('Y-m-d H:i:s', NOW)."\n"
				.$fTimeCost."\n";
			$sMessage = $sHead.$sMessage."\n";
		}

		$sMessage .= "\n";

		return file_put_contents($sFile, $sMessage, $iFlag);
	}
}
