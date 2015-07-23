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
 * MongoDB 连接类
 *
 * sharding，每个数据库只保持一个连接
 *
 * @package
 * @author Zheng Kai <zhengkai@gmail.com>
 */
trait MongoConnect {

	/** MongoClient */
	private $_oConn;

	/** 数组连接池 */
	protected static $_lPoolConn = [];

	/** 数组连接池配置文件，以此区分不同连接 */
	protected static $_lPoolConnConf = [];

	/** _id 的类型，"int" "str" 或者 "bin" */
	private static $_sKeyType = 'int';

	/** _id key */
	private static $_mKey = '_id';

	/** _id value */
	protected $_mID;

	/** Mongo 连接名（取自配置文件） */
	protected $_sConfig;

	/** config */
	protected static $_lConfig;

	/**
	 * __construct
	 *
	 * @access public
	 * @return void
	 */
	public function __construct($mID) {

		self::_getConfig();

		switch (static::$_sKeyType) {
			case 'int':
				$mID = intval($mID);
				break;
			case 'str':
			case 'bin':
				$mID = strval($mID);
				break;
		}

		$this->_sConfig = get_called_class();

		$this->_mID = $mID;
	}

	protected static function _getConfig() {

		$sConfig = get_called_class();

		$aConfig =& static::$_lConfig[$sConfig];
		if ($aConfig) {
			return $aConfig;
		}

		$aConfigFull = Config::get('mongo');

		$aGet =& $aConfigFull['server'][$sConfig];
		if (!is_array($aGet)) {
			throw new TangoException('mongo server config "' . $sConfig . '"');
		}

		$aGet += $aConfigFull['default'];

		$sCustom =& $aGet['custom'];
		if ($sCustom && is_string($sCustom)) {

			$aCustom =& $aConfigFull['custom'][$sCustom];

			if (!is_array($aCustom)) {
				throw new TangoException('mongo server config custom "' . $sCustom . '"');
			}

			$aGet += $aCustom;
		}

		unset($aGet['custom']);
		$aGet += [
			'capacity' => 0,
			'pool' => [],
			'max_capacity' => 0,
		];

		if (!$aGet['pool'] || !is_array($aGet['pool'])) {
			throw new TangoException('mongo config "' . $sConfig . '" empty pool');
		}

		if ($aGet['capacity']) {
			if (static::$_sKeyType != 'int') {
				throw new TangoException('mongo config "' . $sConfig . '" enabled sharding but no int key');
			}
			$aGet['max_capacity'] = $aGet['capacity'] * count($aGet['pool']);
		}

		$aConfig = $aGet;
		return $aConfig;
	}

	/**
	 * _conn
	 *
	 * @access protected
	 * @return \MongoClient
	 */
	protected function _conn() {

		if ($this->_oConn) {
			return $this->_oConn;
		}

		$aConfig = static::_getConfig();

		if ($aConfig['capacity'] > 0) {
			$iShardingID = (int)floor(($this->_mID - 1) / $aConfig['capacity']);
			if ($this->_mID > $aConfig['max_capacity']) {
				throw new TangoException(
					'mongo ' . get_class() . ' sharding id overflow '
					. $iShardingID . '/' . count($aConfig['pool'])
				);
			}
		} else {
			$iShardingID = 0;
		}
		$sHost =& $aConfig['pool'][$iShardingID];
		if (!$sHost) {
			throw new TangoException(
				'mongo ' . get_class() . 'sharding id overflow '
				. $iShardingID . '/' . count($aConfig['pool'])
			);
		}

		$iKey = array_search($sHost, self::$_lPoolConnConf);
		if (!is_int($iKey)) {

			$iKey = count(self::$_lPoolConnConf);
			self::$_lPoolConnConf[$iKey] = $sHost;

			self::$_lPoolConn[$iKey] = new \MongoClient('mongodb://' . $sHost);
		}

		$this->_oConn =& self::$_lPoolConn[$iKey];
		return $this->_oConn;
	}

	public function _coll() {

		$aConfig = static::_getConfig();
		return $this
			->_conn()
			->selectCollection($aConfig['db'], $aConfig['collection']);
	}

	protected static function _sharding() {
		return 0;
	}
}
