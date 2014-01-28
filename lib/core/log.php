<?php
namespace Tango\Core;

use Tango\Core\TangoException;

Config::setFileDefault('log', dirname(__DIR__).'/config/log.php');

class Log {

	static $_bEnable;
	static $_sDebugPath;

	static $_lType = [
		1 => 'db',
		2 => 'cache',
		3 => 'php',
	];
	static $_iAI;
	static $_iStep;

	static public function init() {

		if (self::$_bEnable !== NULL) {
			return self::$_bEnable;
		}

		$aConfig = Config::get('log');

		if (!$aConfig['enable']) {
			return self::$_bEnable = FALSE;
		}

		$sPath = rtrim(trim($aConfig['debug_path']), '/');
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

	static public function debug($sType, $sMessage, $bHead = FALSE) {

		if (!self::init()) {
			return FALSE;
		}

		if (!is_string($sType) && !preg_match('#[0-9a-z\_]{1, 30}#', $sType)) {
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

		file_put_contents(self::$_sDebugPath.'/'.$sType, $sMessage, FILE_APPEND | LOCK_EX);
	}

	static public function collection($sType, array $aMessage) {

		if (!self::init()) {
			return FALSE;
		}

		$iType = array_search(self::$_lType, $sType, TRUE);
		if (!in_array($iType, self::$_lType)) {
			throw new TangoException('unknown type "'.$sType.'"');
		}

		$oDB = DB::getInstance('_debug');
		if (!self::$_iAI) {
			self::$_iAI = $oDB->getAI('id_gen');
		}
		self::$_iStep++;

	}
}
