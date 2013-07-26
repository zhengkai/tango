<?php
namespace Tango\Addon;

use Tango\Core\TangoException;
use Tango\Drive\MC;
use Tango\Drive\DB;

/*
	基本上所有的数据都是这么一个流程：

	两次缓存（脚本内变量缓存/memcache）
	一次 MySQL

	哪个近取哪个
 */

class Cache {

	protected static $_lConfig = [];
	protected static $_lPool = [];

	protected static $_iExpireDefault = 172800; // 两天

	protected static $_lFetchType = [
		"getRow",
		"getAll",
		"getSingle",
	];

	protected static $_bMemcache = TRUE;
	// protected static $_bMemcache = FALSE;

	protected static $_bDebug = TRUE;
	protected static $_lLog = [];

	public static function dumpPool() {
		return self::$_lPool;
	}

	public static function config($sName, $aConfig) {

		$aConfigRow =& self::$_lConfig[$sName];

		if ($aConfigRow) { // 不得重复 config
			throw new TangoException(
				'Duplicate config for "'.$sName.'", '
				.'First'.self::_configDebugLoc($aConfigRow)
			);
		}

		if (is_object($aConfig)) {
			$aConfig = [
				'func' => $aConfig,
			];
		} else if (!is_array($aConfig)) {
			throw new TangoException('Config must be an Array or a Callback Function');
		}

		if (empty($aConfig['key']) && !empty($aConfig['arg_num']) && ($aConfig['arg_num'] > 1)) {
			send_error('must be define "key"');
		}

		$aConfig += [ // 默认值
			'key'     => NULL,
			'expire'  => self::$_iExpireDefault,
			'arg_num' => 1,
			'func'    => NULL,
			'db'      => NULL,
			'query'   => NULL,
			'fetch_type' => self::$_lFetchType[0],
			'save_when_false' => TRUE, // 如果结果为 false，则不缓存
		];

		$aDebug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
		$aConfig['debug'] = [
			'file' => $aDebug['file'],
			'line' => $aDebug['line'],
		];

		if (!$aConfig['func'] && (!$aConfig['db'] || !$aConfig['query'])) {
			send_error('No func or db/query in config "'.$sName.'"');
		}

		if (!in_array($aConfig['fetch_type'], self::$_lFetchType)) {
			send_error(
				'unknown fetch_type "'.$aConfig['fetch_type'].'" '
				.'(should be one of ['.implode(', ', self::$_lFetchType).'] ).'
			);
		}

		$aConfigRow = $aConfig;
	}

	public static function _configDebugLoc($aConfig) {
		return ' in file '.$aConfig['debug']['file'].' '
			.'on line '.$aConfig['debug']['line'];
	}

	public static function get() {

		$mArgs = func_get_args();
		$sName = array_shift($mArgs);

		// trigger_error(print_r($mArgs, 1));

		$aConfig = self::_getConfig($sName, $mArgs);

		if (!is_string($aConfig['key'])) {

			trigger_error(print_r($aConfig, 1));
			send_error('error when get');
			exit;
		}

		if (array_key_exists($aConfig['key'], self::$_lPool)) {
			return self::$_lPool[$aConfig['key']];
		}

		$aData =& self::$_lPool[$aConfig['key']];

		if (self::$_bMemcache) {
			$oMC = \Tango\Drive\MC::getInstance();
			$fTime = microtime(TRUE);
			$aData = $oMC->get($aConfig['key']);
			$fTime = microtime(TRUE) - $fTime;
			$bHit = $oMC->getResultCode() === \Memcached::RES_SUCCESS;
			self::_addDebugLog($aConfig, $bHit, $fTime);
			if ($bHit) {
				return $aData;
			}
		}

		if ($aConfig['func']) {
			$aData = call_user_func_array($aConfig['func'], $mArgs);
		} else {
			$oDB = DB::getInstance($aConfig['db']);
			$aData = call_user_func([$oDB, $aConfig['fetch_type']], $aConfig['query'], $mArgs);
		}

		if (
			self::$_bMemcache
			&& ($aData || $aConfig['save_when_false'])
			) {

			$oMC->set($aConfig['key'], $aData, $aConfig['expire']);
		}

		return $aData;
	}

	public static function clean($sName, $mArgs = NULL) {

		$mArgs = func_get_args();
		$sName = array_shift($mArgs);

		$aConfig = self::_getConfig($sName, $mArgs);

		unset(self::$_lPool[$aConfig['key']]);

		if (self::$_bMemcache) {
			$oMC = MCz::getInstance();
			$aData = $oMC->delete($aConfig['key']);
		}
	}

	public static function cleanAll($sName) {

		if (!TANGO_DEV) {
			return FALSE;
		}

		self::$_lPool = [];
		if (self::$_bMemcache) {
			$oMC = MCz::getInstance();
			$oMC->flush();
		}

		return TRUE;
	}

	protected static function _getConfig($sName, $mArgs) {

		if (!array_key_exists($sName, self::$_lConfig)) {
			send_error('no setting for cache '.$sName);
		}

		$aConfig = self::$_lConfig[$sName];

		if (($iArgs = count($mArgs)) !== $aConfig['arg_num']) {
			$sError = 'Cache "'.$sName.'" arg_num not match '
				.'( setting '.$aConfig['arg_num'].' and input '.$iArgs.' ). '
				.'Config'.self::_configDebugLoc($aConfig);
			throw new TangoException($sError, 2);
		}

		if ($mArgs === NULL) {
			if (!$aConfig['key']) {
				$aConfig['key'] = $sName;
			}
			return $aConfig;
		}

		if (!$aConfig['key']) {
			$aConfig['key'] = $sName.'_%d';
		}

		if ($mArgs) {
			$aConfig['key'] = vsprintf($aConfig['key'], $mArgs);
		}

		return $aConfig;
	}

	public static function getDebugLog() {

		return self::$_lLog;
	}

	protected static function _addDebugLog($aConfig, $bHit, $fTime) {

		if (!self::$_bDebug) {
			return FALSE;
		}

		self::$_lLog[] = array(
			'key' => $aConfig['key'],
			'hit' => $bHit,
			'time' => $fTime,
		);

		return TRUE;
	}
}
