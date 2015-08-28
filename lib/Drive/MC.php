<?php
/**
 * This file is part of the Tango Framework.
 *
 * (c) Zheng Kai <zhengkai@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tango\Drive;

use Tango\Core\Config;

Config::setFileDefault('memcache', dirname(__DIR__).'/Config/memcache.php');

/**
 * MC
 *
 * memcached wrapper
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class MC {

	/** 对应 config 中的命名 */
	private static $_sConfig = 'default';

	/** 单例连接 */
	private static $_oConn;

	/** 用于延迟 touch 的 key 列表 */
	private static $_lTouch = [];

	/** 连接配置 */
	protected static $_aOption = [
		\Memcached::OPT_BINARY_PROTOCOL => TRUE,
		\Memcached::OPT_COMPRESSION => TRUE,
		\Memcached::OPT_LIBKETAMA_COMPATIBLE => FALSE,
		\Memcached::OPT_PREFIX_KEY => 't_',
		\Memcached::OPT_CONNECT_TIMEOUT => 300,
	];

	/**
	 * 创建单例连接
	 *
	 * @static
	 * @access public
	 * @return \Memcached
	 */
	public static function conn() {

		if (static::$_oConn !== NULL) {
			return static::$_oConn;
		}

		$aConfig = Config::get('memcache');
		$lServer =& $aConfig['server'][static::$_sConfig];
		if (!is_array($lServer)) {
			throw new Exception('memcache config "' . static::$_sConfig . '" not found');
		}

		$oConn = new \Memcached();
		$oConn->setOptions(static::$_aOption);
		$oConn->addServers($lServer);

		return static::$_oConn = $oConn;
	}

	/**
	 * get
	 *
	 * @param string $sKey
	 * @static
	 * @access public
	 * @return mixed
	 */
	public static function get(string $sKey) {
		return static::conn()->get($sKey);
	}

	/**
	 * getMulti
	 *
	 * @param array $lKey
	 * @static
	 * @access public
	 * @return array
	 */
	public static function getMulti(array $lKey) {
		return static::conn()->getMulti($lKey);
	}

	/**
	 * getResultCode
	 *
	 * @static
	 * @access public
	 * @return integer
	 */
	public static function getResultCode() {
		return static::conn()->getResultCode();
	}

	/**
	 * set
	 *
	 * @param string $sKey
	 * @param mixed $mValue
	 * @param int $iExpire
	 * @static
	 * @access public
	 * @return boolean
	 */
	public static function set(string $sKey, $mValue, int $iExpire = 86400) {
		return static::conn()->set($sKey, $mValue, $iExpire);
	}

	/**
	 * setMulti
	 *
	 * @param array $lItem
	 * @param int $iExpire
	 * @static
	 * @access public
	 * @return boolean
	 */
	public static function setMulti(array $lItem, int $iExpire = 86400) {
		return static::conn()->setMulti($lItem, $iExpire);
	}

	/**
	 * add
	 *
	 * @param string $sKey
	 * @param mixed $mValue
	 * @param int $iExpire
	 * @static
	 * @access public
	 * @return boolean
	 */
	public static function add(string $sKey, $mValue, int $iExpire = 86400) {
		return static::conn()->add($sKey, $mValue, $iExpire);
	}

	/**
	 * 记录要 touch 的 key，以便脚本结束时再执行
	 * 注意要配合 self::touchDo() 使用（如在 fastcgi_finish_request() 后执行）
	 *
	 * @param mixed $sKey
	 * @param int $iExpire
	 * @static
	 * @access public
	 * @return void
	 */
	public static function touchDelay(string $sKey, int $iExpire = 86400) {
		static::$_lTouch[$sKey] = $iExpire;
	}

	/**
	 * 遍历 touch 之前记录的 key
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function touchDo() {
		foreach (static::$_lTouch as $sKey => $iExpire) {
			static::conn()->touch($sKey, $iExpire);
		}
		static::$_lTouch = [];
	}
}
