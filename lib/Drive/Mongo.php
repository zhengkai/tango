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
	private $_oConn;

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
	protected $_sConfig;

	/** config */
	protected static $_lConfig;

	/** sharding */
	private static $_bSharding = FALSE;

	private static $_lDiff = [];

	protected static $_lIncKey = [];

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

	public function get() {

		$v =& self::$_lPoolDataChange[$this->_sConfig][$this->_mID];

		if (!is_array($v)) {

			$o = $this->_coll();

			$aOrig = $o->findOne([static::$_mKey => $this->_mID]) ?: FALSE;

			$bNeedInit = !is_array($aOrig);
			if (!$bNeedInit) {
				unset($aOrig[static::$_mKey]);
			}

			self::$_lPoolData[$this->_sConfig][$this->_mID] = $aOrig;

			if ($bNeedInit) {
				$v = $this->_init();
			} else {
				$v = $aOrig;
			}

			$v = $this->_format($v);
		}

		return $v;
	}

	public function update($aUpdate) {

		$v =& self::$_lPoolDataChange[$this->_sConfig][$this->_mID];
		if (!is_array($v)) {
			$this->get();
		}
		return $v = self::_update($v, $aUpdate);
	}

	public function save() {

		$aUpdate =& self::$_lPoolDataChange[$this->_sConfig][$this->_mID];
		$aOrig   =& self::$_lPoolData[$this->_sConfig][$this->_mID];

		if ($aUpdate === $aOrig) {
			return FALSE;
		}

		$aOP = ($aOrig === FALSE) ? $aUpdate : self::_getDiff($aOrig, $aUpdate);

		$aOrig = $aUpdate;

		return $this->_coll()->update(
			[static::$_mKey => $this->_mID],
			$aOP,
			['upsert' => TRUE]
		);
	}

	/**
	 * 保存所有改动（所有继承自该类的）
	 *
	 * @static
	 * @access public
	 * @return int 改动条数
	 */
	public static function saveAll() {
		$i = 0;
		foreach (self::$_lPoolDataChange as $sConfig => $lPool) {
			foreach ($lPool as $mID => $aChange) {
				if ($aChange === self::$_lPoolData[$sConfig][$mID]) {
					$o = new $sConfig();
					$o->save();
					$i++;
				}
			}
		}
		return $i;
	}

	/**
	 * 对 _getDiff 的外层包装
	 *
	 * @param array $a
	 * @param array $b
	 * @static
	 * @access public
	 * @return array
	 */
	protected static function _getDiff(array $a, array $b) {

		self::$_lDiff = [
			'$set' => [],
			'$unset' => [],
		];

		self::_getDiffRecursion($a, $b);

		self::$_lDiff['$set'] = array_map(function ($v) {
			if ($v === []) {
				return new stdClass();
			}
			return $v;
		}, self::$_lDiff['$set']);

		self::$_lDiff = array_filter(self::$_lDiff);

		return self::$_lDiff;
	}

	/**
	 * 如果 mongo 里的数据要从 a 变成 b，生成操作语句
	 *
	 * @param array $a
	 * @param array $b
	 * @param string $sPath
	 * @static
	 * @access public
	 * @return array
	 */
	protected static function _getDiffRecursion(array $a, array $b, $sPath = '') {

		// 如果 key 完全不一样，直接替换，不再对比里面每一项
		if ($sPath) {
			// 为处理方便，根节点略过
			// 因为整个根节点所有 key 都变化的情况极少发生，
			// 也避免了针对根节点的 set 写更多特殊处理
			$bReplace = TRUE;
			foreach ($a as $k => $v) {
				if (array_key_exists($k, $b)) {
					$bReplace = FALSE;
					break;
				}
			}
			if ($bReplace) {
				self::$_lDiff['$set'][$sPath] = $b;
				return TRUE;
			}
		}

		// 以 a 为基准，看 a 的值里哪些变成了 b 的
		foreach ($a as $k => $v) {

			$sPathNow = $sPath ? $sPath . '.' . $k : $k;

			// b 里已经没有该 key，删除
			if (!array_key_exists($k, $b)) {
				self::$_lDiff['$unset'][$sPathNow] = TRUE;
				continue;
			}

			// 整个节点都没有变化，跳过
			$new = $b[$k];
			unset($b[$k]);
			if ($new === $v) {
				continue;
			}

			// 都是数组，进入下一层比较
			if (is_array($v) && is_array($new)) {
				self::_getDiffRecursion($v, $new, $sPathNow);
				continue;
			}

			// path 在 _lIncKey 里的，计算差值，而不是赋值
			if (in_array($sPathNow, static::$_lIncKey)) {
				if (!is_numeric($new) || !is_numeric($v)) {
					throw new TangoException('no number in path = ' . $sPathNow);
				}
				self::$_lDiff['$inc'][$sPathNow] = $new - $v;
				continue;
			}

			// 类型不同（一个是数组，另一个不是），直接覆盖
			self::$_lDiff['$set'][$sPathNow] = $new;
		}

		// b 里新增的
		foreach ($b as $k => $v) {
			self::$_lDiff['$set'][$sPath ? $sPath . '.' . $k : $k] = $v;
		}
	}

	protected function _init() {
		return [];
	}

	protected function _format(array $a) {
		return $a;
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

	/**
	 * 更新数组
	 *
	 * @param array $aData
	 * @param array $aUpdate
	 * @param string $sPath
	 * @static
	 * @access public
	 * @return array
	 */
	public static function _update(array $aData, array $aUpdate, $sPath = '') {

		foreach ($aUpdate as $sKey => $mVal) {

			// key 里不能带 $ 和 . 这两个特殊符号
			if (strpos($sKey, '$') !== FALSE || strpos($sKey, '.') !== FALSE) {
				throw new TangoException('illegal characters in key = "' . self::_path($sPath, $sKey) . '"');
			}

			$mCurrent =& $aData[$sKey];

			// 没有老值，直接赋新值
			if ($mCurrent === NULL) {
				if (is_array($mVal) && !self::checkKey($mVal)) {
					throw new TangoException('array not match when updating path = "' . self::_path($sPath, $sKey) . '"');
				}
				$mCurrent = $mVal;
				continue;
			}

			$bArray = is_array($mVal);

			// 如果一个是 array 而另外一个不是，报错
			if ($bArray != is_array($mCurrent)) {
				throw new TangoException('array not match when updating path = "' . self::_path($sPath, $sKey) . '"');
			}

			// 如果是数组，则走递归
			if ($bArray) {
				$aData[$sKey] = self::_update($aData[$sKey], $mVal, self::_path($sPath, $sKey));
				continue;
			}

			// 如果上述情况都不存在，直接赋值
			$aData[$sKey] = $mVal;
		}

		return $aData;
	}

	protected static function _path($sPath, $sSub) {
		return $sPath ? $sPath . '.' . $sSub : $sSub;
	}

	/**
	 * 检查数组的 key 里有没有异常字符
	 *
	 * @param array $aData
	 * @static
	 * @access public
	 * @return boolean
	 */
	public static function checkKey(array $aData) {

		foreach ($aData as $sKey => $mVal) {
			if (strpos($sKey, '$') !== FALSE || strpos($sKey, '.') !== FALSE) {
				return FALSE;
			}
			if (is_array($mVal) && !self::checkKey($mVal)) {
				return FALSE;
			}
		}
		return TRUE;
	}

	/**
	 * 根据指定路径生成多层（每层只有一个元素）的数组
	 *
	 * @param array $lPath
	 * @param mixed $mVal
	 * @static
	 * @access public
	 * @return void
	 */
	public static function arrayPath(array $lPath, $mVal) {
		$aReturn = [];
		$mCurrent =& $aReturn;
		foreach ($lPath as $sKey) {
			if (strpos($sKey, '$') !== FALSE || strpos($sKey, '.') !== FALSE) {
				throw new TangoException('illegal character in key = "' . $sKey . '"');
			}
			$mCurrent =& $mCurrent[$sKey];
		}
		$mCurrent = $mVal;
		return $aReturn;
	}
}
