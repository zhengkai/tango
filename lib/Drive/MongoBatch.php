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

use Tango\Core\TangoException;

/**
 * MongoDB Batch类
 *
 * 多次更改，一次读写
 * 必须自己做额外的锁来防止互相覆盖的情况
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */
trait MongoBatch {

	/** 内存数组缓存 */
	protected static $_lPoolData = [];

	/** 内存数组缓存（修改后） */
	protected static $_lPoolDataChange = [];

	/** 递归整个数组是用来存储所有需要改动的 path */
	private static $_lDiff = [];

	/** 那些字段是可以差值（$inc 而非 $set）的方式更新 */
	protected static $_lIncKey = [];

	/**
	 * 获取整个 document
	 *
	 * @access public
	 * @return array
	 */
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

	/**
	 * 更新某些值
	 *
	 * @param array $aUpdate
	 * @access public
	 * @return array
	 */
	public function update(array $aUpdate) {

		$v =& self::$_lPoolDataChange[$this->_sConfig][$this->_mID];
		if (!is_array($v)) {
			$this->get();
		}
		return $v = self::_update($v, $aUpdate);
	}

	/**
	 * 保存改动
	 *
	 * @access public
	 * @return array
	 */
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

	/**
	 * 如果 document 为空时，如何初始化
	 *
	 * @access protected
	 * @return array
	 */
	protected function _init() {
		return [];
	}

	/**
	 * 每次加载时跑一遍，用于改造老数据
	 *
	 * @param array $a
	 * @access protected
	 * @return array
	 */
	protected function _format(array $a) {
		return $a;
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

	/**
	 * 用于生成点号分隔的路径
	 *
	 * @param mixed $sPath
	 * @param mixed $sSub
	 * @static
	 * @access protected
	 * @return void
	 */
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
