<?php
/**
 * This file is part of the Tango Framework.
 *
 * (c) Zheng Kai <zhengkai@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tango\Core;

Config::setFileDefault('debug', dirname(__DIR__).'/Config/debug.php');

/**
 * Debug
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class Debug {

	/** 是否允许 debug */
	protected static $_bEnable;

	/** debug 文件保存路径 */
	protected static $_sDebugPath;

	/**
	 * 初始化，读取配置，来决定是否 debug、如何 debug
	 *
	 * @static
	 * @access public
	 * @return bool
	 */
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

	/**
	 * 增加一条 debug 记录（追加）
	 *
	 * @param mixed $sType
	 * @param mixed $sMessage
	 * @param mixed $bHead
	 * @static
	 * @access public
	 * @return void
	 */
	public static function add($sType, $sMessage = NULL, $bHead = FALSE) {
		if ($sMessage === NULL) {
			return ;
		}
		self::_file($sType, $sMessage, $bHead, FILE_APPEND | LOCK_EX);
	}

	/**
	 * 保存一条 debug 记录（覆盖）
	 *
	 * @param mixed $sType
	 * @param mixed $sMessage
	 * @param mixed $bHead
	 * @static
	 * @access public
	 * @return void
	 */
	public static function dump($sType, $sMessage = NULL, $bHead = FALSE) {
		if ($sMessage === NULL) {
			return ;
		}
		self::_file($sType, $sMessage, $bHead, LOCK_EX);
	}

	/**
	 * 具体如何写文件
	 *
	 * @param mixed $sType
	 * @param mixed $sMessage
	 * @param mixed $bHead
	 * @param mixed $iFlag
	 * @static
	 * @access public
	 * @return bool
	 */
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
				.date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'])."\n"
				.$fTimeCost."\n";
			$sMessage = $sHead.$sMessage."\n";
		}

		$sMessage .= "\n";

		return file_put_contents($sFile, $sMessage, $iFlag);
	}
}
