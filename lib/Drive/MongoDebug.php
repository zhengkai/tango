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

/**
 * MongoDebug 用于做白盒测试的 debug 类
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */
trait MongoDebug {

	/**
	 * 确认类的创建信息
	 *
	 * @access public
	 * @return void
	 */
	public function hello() {
		echo "\n", 'config: ', $this->_sConfig, "\n";
		$o = $this->_conn();
		echo ' class: ', get_class($o), "\n";
	}

	/**
	 * 公开整个连接池信息
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function debugPool() {
		return [
			'conf' => self::$_lPoolConnConf,
			'conn' => self::$_lPoolConn,
		];
	}

	/**
	 * 将 MongoConnect::_getConfig 方法公开
	 *
	 * @static
	 * @access public
	 * @return array
	 */
	public static function debugConfig() {
		$aConfig = self::_getConfig();
		return [
			'full' => self::$_lConfig,
			'current' => $aConfig,
		];
	}

	/**
	 * 将 MongoConnect::_conn 方法公开
	 *
	 * @access public
	 * @return \MongoClient
	 */
	public function debugConn() {
		return $this->_conn();
	}

	/**
	 * 将 MongoConnect::_coll 方法公开
	 *
	 * @access public
	 * @return \MongoCollection
	 */
	public function debugColl() {
		return $this->_coll();
	}

	/**
	 * 直接对 mongo 做 get 即时操作
	 *
	 * @access public
	 * @return array
	 */
	public function debugGet() {
		$aResult = $this->_coll()->findOne([static::$_mKey => $this->_mID]);
		unset($aResult[static::$_mKey]);
		return $aResult;
	}

	/**
	 * 直接对 mongo 做 set 即时操作
	 *
	 * @param array $aData
	 * @access public
	 * @return array
	 */
	public function debugSet(array $aData = []) {

		$sConfig = get_called_class();

		static::$_lPoolDataChange[$sConfig][$this->_mID] = $aData;
		static::$_lPoolData[$sConfig][$this->_mID] = $aData;
		$this->_coll()->remove([static::$_mKey => $this->_mID]);
		return $this->_coll()->update(
			[static::$_mKey => $this->_mID],
			$aData,
			['upsert' => TRUE]
		);
	}

	/**
	 * 直接对 mongo 做 update 即时操作
	 *
	 * @param array $aData
	 * @access public
	 * @return void
	 */
	public function debugUpdate(array $aData = []) {
		return $this->_coll()->update(
			[static::$_mKey => $this->_mID],
			$aData
		);
	}

	/**
	 * 清除数组缓存 _lPoolData _lPoolDataChange
	 *
	 * @access public
	 * @return void
	 */
	public function debugClose() {

		$sConfig = get_called_class();

		unset(static::$_lPoolDataChange[$sConfig][$this->_mID]);
		unset(static::$_lPoolData[$sConfig][$this->_mID]);
	}

	/**
	 * 将 MongoBatch::_getDiff 方法公开
	 *
	 * @param array $a
	 * @param array $b
	 * @static
	 * @access public
	 * @return array
	 */
	public static function debugDiff(array $a, array $b) {
		return self::_getDiff($a, $b);
	}
}
