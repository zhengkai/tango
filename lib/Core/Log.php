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

use Tango\Drive\DB;

Config::setFileDefault('log', dirname(__DIR__).'/Config/log.php');

settype($_SERVER['SERVER_ADDR'], 'string');
settype($_SERVER['REMOTE_ADDR'], 'string');
settype($_SERVER['REQUEST_URI'], 'string');

/**
 * 日志
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class Log {

	/** 是否允许记录 log（通过配置文件，或者记录超过 1000 次时关闭） */
	static $_bEnable;

	/** debug 文件保存路径 */
	static $_sDebugPath;

	/** 日志类型 */
	static $_lType = [
		1 => 'db',
		2 => 'cache',
		3 => 'php',
		4 => 'tmp',
	];

	/** 脚本运行 id，id 相同的为同一次脚本执行记录，靠 MySQL 来区分，不会重复 */
	static $_iAI;
	/** 单次脚本中第几次记录 log，超过 1000 次的部分丢弃 */
	static $_iStep = 0;

	/**
	 * 初始化
	 *
	 * @static
	 * @access public
	 * @return bool
	 */
	public static function init() {

		if (self::$_bEnable !== NULL) {
			return self::$_bEnable;
		}

		$aConfig = Config::get('log');

		if (!$aConfig['enable']) {
			return self::$_bEnable = FALSE;
		}

		$sPath = trim($aConfig['debug_path']);
		$sPath = Util::getTmpPath($sPath);
		$sPath = rtrim($sPath, '/');
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

		self::$_sDebugPath = $sPath . '/';

		return self::$_bEnable = TRUE;
	}

	/**
	 * debug
	 *
	 * @param string $sType 日志类型，参见 getType()
	 * @param array|string $sMessage 日志内容
	 * @param boolean $bHead 是否加入统一的头信息
	 * @static
	 * @access public
	 * @return bool
	 */
	public static function debug($sType, $sMessage, $bHead = FALSE) {

		if (!self::init()) {
			return FALSE;
		}

		if (!is_string($sType) && !preg_match('#[0-9a-z\_]{1, 30}#', $sType)) {
			return FALSE;
		}

		$sFile = self::$_sDebugPath . $sType;

		if (!self::_prepareWriteFile($sFile)) {
			return FALSE;
		}

		if (!is_string($sMessage)) {
			$sMessage = json_encode($sMessage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}

		if ($bHead) {

			$fTime = microtime(TRUE);

			$sTime = date(Config::get('log')['time_format'], $fTime);
			$sTime .= substr(sprintf('%.03f' ,$fTime), -4);

			$fTimeCost = sprintf('%.06f', $fTime - $_SERVER['REQUEST_TIME_FLOAT']);
			$sHead = '[' . $sTime . '] [' . $fTimeCost . '] ' . "\n"
				. ($_SERVER['REQUEST_URI'] ?: $_SERVER['SCRIPT_FILENAME'])."\n"
				. "\n";
			$sMessage = $sHead.$sMessage."\n";
		}

		$sMessage .= "\n";

		return file_put_contents($sFile, $sMessage, FILE_APPEND | LOCK_EX);
	}

	/**
	 * 检查是否可以往某个地址写 log（磁盘满了、文件过大、文件写保护等检查）
	 *
	 * @param string $sFile
	 * @static
	 * @access protected
	 * @return boolean
	 */
	protected static function _prepareWriteFile($sFile) {

		$aConfig = Config::get('log');

		$sSpaceCheck = $sFile;

		if (file_exists($sFile)) {
			if (!is_writable($sFile)) {
				return FALSE;
			}
			if (filesize($sFile) > $aConfig['max_size']) {
				return self::$_bEnable = FALSE;
			}
		} else {
			$sSpaceCheck = dirname($sFile);
		}

		if (($iSpace = disk_free_space($sSpaceCheck)) < $aConfig['disk_free_space']) {
			// throw new Exception('not enough free disk space ' . $iSpace . '/' . $aConfig['disk_free_space']);
			return self::$_bEnable = FALSE;
		}

		return TRUE;
	}

	/**
	 * 列举日志类型
	 *
	 * @static
	 * @access public
	 * @return array
	 */
	public static function getType() {
		return self::$_lType;
	}

	/**
	 * 将比较重要的 log 记数据库
	 *
	 * @param string $sType 日志类型，参见 getType()
	 * @param array $aMessage 日志内容
	 * @static
	 * @access public
	 * @throws TangoException
	 * @return bool
	 */
	public static function collection($sType, array $aMessage) {

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
				'time' => $_SERVER['REQUEST_TIME'],
				'elapsed' => microtime(TRUE) - $_SERVER['REQUEST_TIME_FLOAT'],
			];
		}

		$sQuery = 'INSERT INTO log '
			.'SET id = '.self::$_iAI.', '
			.'step = '.self::$_iStep.', '
			.'type = '.$iType.', '
			.'data = 0x'.bin2hex(gzcompress(json_encode($aMessage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
		return $oDB->exec($sQuery);
	}
}
