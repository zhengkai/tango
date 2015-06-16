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
use Tango\Core\TangoException;

Config::setFileDefault('mongo', dirname(__DIR__) . '/Config/mongo.php');

/**
 * MongoDB 基础类
 *
 * 主要解决问题：多次更改，一次读写
 *
 * @package
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class Mongo {

	/** MongoClient */
	private static $_oConn;

	/** 数组连接池 */
	protected static $_lPoolConn = [];

	/** 数组连接池配置文件，以此区分不同连接 */
	protected static $_lPoolConnConf = [];

	/** 内存数组缓存 */
	protected static $_lPoolData = [];

	/** 内存数组缓存（修改后） */
	protected static $_lPoolDataChange = [];

	/** _id 的类型，"int" "str" 或者 "bin" */
	private static $_sKeyType = 'int';

	/** _id key */
	private static $_mKey = '_id';

	/** _id value */
	protected $_mID;

	/** Mongo 连接名（取自配置文件） */
	private static $_sConfig;

	/** config */
	private static $_aConfig;

	/** sharding */
	private static $_bSharding = FALSE;

	/**
	 * __construct
	 *
	 * @access public
	 * @return void
	 */
	public function __construct($mID) {

		if (!static::$_aConfig) {
			$aConfigFull = Config::get('mongo');
			$aConfig =& $aConfigFull['server'][static::$_sConfig];
			if (!is_array($aConfig)) {
				throw new TangoException('mongo server config "' . static::$_sConfig . '"');
			}
			$aConfig += $aConfigFull['default'];
			static::$_aConfig = $aConfig;
		}

		switch (static::$_sKeyType) {
			case 'int':
				$mID = intval($mID);
				break;
			case 'str':
			case 'bin':
				$mID = strval($mID);
				break;
		}

		$this->_mID = $mID;

		$this->_conn();
	}

	public function hello() {
		echo 'config: ' . static::$_sConfig, "\n";
		$o = $this->_conn();
		echo ' class: ' . get_class($o), "\n";
	}

	public function get() {

		$v =& static::$_lPoolDataChange[static::$_sConfig][$this->_mID];

		if (!is_array($v)) {

			$o = $this->_coll();

			$aOrig = $o->findOne([static::$_mKey => $this->_mID]) ?: [];
			unset($aOrig[static::$_mKey]);

			static::$_lPoolData[static::$_sConfig][$this->_mID] = $aOrig;

			$v = $this->_init($aOrig);
		}

		return $v;
	}

	public function update($aUpdate) {
		$v =& static::$_lPoolDataChange[static::$_sConfig][$this->_mID];
		if (!is_array($v)) {
			$v->get();
		}
		$v = $aUpdate + $v;
	}

	public function save() {
		$v =& static::$_lPoolDataChange[static::$_sConfig][$this->_mID];
		$o = $this->_coll();
		return $o->update(
			[static::$_mKey => $this->_mID],
			$v,
			['upsert' => TRUE]
		);
	}

	protected function _init($v) {
		return $v;
	}

	/**
	 * _conn
	 *
	 * @access protected
	 * @return \MongoClient
	 */
	protected function _conn() {

		if (!static::$_oConn) {

			$aConfig = static::$_aConfig;
			unset($aConfig['db']);
			unset($aConfig['collection']);
			unset($aConfig['debug']);
			ksort($aConfig);

			$iKey = array_search($aConfig, self::$_lPoolConnConf);
			if (!is_int($iKey)) {
				$iKey = count(self::$_lPoolConnConf);
				self::$_lPoolConnConf[$iKey] = $aConfig;

				self::$_lPoolConn[$iKey] = new \MongoClient();
			}

			static::$_oConn =& self::$_lPoolConn[$iKey];
		}
		return static::$_oConn;
	}

	protected function _coll() {
		return $this
			->_conn()
			->selectCollection(static::$_aConfig['db'], static::$_aConfig['collection']);
	}

	protected static function _shading() {
		return 1;
	}
}
