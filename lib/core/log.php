<?php
namespace Tango\Core;

use Tango\Core\TangoException;
use Tango\Drive\DB;

Config::setFileDefault('log', dirname(__DIR__).'/config/log.php');

settype($_SERVER['SERVER_ADDR'], 'string');
settype($_SERVER['REMOTE_ADDR'], 'string');
settype($_SERVER['REQUEST_URI'], 'string');

class Log {

	static $_bEnable;
	static $_sDebugPath;

	static $_lType = [
		1 => 'db',
		2 => 'cache',
		3 => 'php',
		4 => 'tmp',
	];
	static $_iAI;
	static $_iStep = 0;

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

		$aConfig = Config::get('log');

		$sFile = self::$_sDebugPath.'/'.$sType;
		if (disk_free_space(self::$_sDebugPath) < $aConfig['disk_free_space']) {
			return FALSE;
		}

		if (file_exists($sFile) && filesize($sFile) > $aConfig['max_size']) {
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

		return file_put_contents($sFile, $sMessage, FILE_APPEND | LOCK_EX);
	}

	static public function getType() {
		return self::$_lType;
	}

	static public function collection($sType, array $aMessage) {

		self::$_iStep++;
		if (self::$_iStep > 1000) {
			return FALSE;
		}

		if (!self::init()) {
			return FALSE;
		}

		$iType = array_search((string)$sType, self::$_lType);
		if (!$iType) {
			throw new TangoException('unknown type "'.$sType.'"');
		}

		$oDB = DB::getInstance('_debug');
		if (!self::$_iAI) {
			self::$_iAI = $oDB->genAI('id_gen');
		}

		if (self::$_iStep == 1) {
			$aMessage['_init'] = [
				'sapi' => PHP_SAPI,
				'server_ip' => $_SERVER['SERVER_ADDR'],
				'client_ip' => $_SERVER['REMOTE_ADDR'],
				'uri' => $_SERVER['REQUEST_URI'],
				'time' => NOW,
				'elapsed' => microtime(TRUE) - $_SERVER['REQUEST_TIME_FLOAT'],
			];
		}

		$sQuery = 'INSERT INTO log '
			.'SET id = '.self::$_iAI.', '
			.'step = '.self::$_iStep.', '
			.'type = '.$iType.', '
			.'data = 0x'.bin2hex(gzcompress(json_encode($aMessage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
		$oDB->exec($sQuery);
	}
}
