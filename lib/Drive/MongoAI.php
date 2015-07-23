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

class MongoAI {

	use MongoConnect;

	/**
	 * 原子操作的自增id
	 *
	 * @param mixed $sKey
	 * @param array $aOption
	 * @access public
	 * @return void
	 */
	function gen($sKey, array $aOption = []) {

		if (!self::_checkKey($sKey)) {
			throw TangoException('illegal key name');
		}

		$aOption += [
			'init' => 1,
			'step' => 1,
		];

		$aQuery = [
			'$and' => [
				[static::$_mKey => $this->_mID],
				[$sKey => ['$exists' => TRUE]],
			],
		];
		$aUpdate = ['$inc' => [$sKey => $aOption['step']]];
		$aField = [$sKey => TRUE];
		$aFindOption = ['new' => TRUE];

		$aResult = $this->_coll()
			->findAndModify($aQuery, $aUpdate, $aField, $aFindOption);

		if ($aResult && isset($aResult[$sKey])) {
			return $aResult[$sKey];
		}

		/*
		$aResult = $this->_coll()
			->update([static::$_mKey => $this->_mID], ['$set' => [$sKey => 0]], ['upsert' => TRUE]);
		 */

		try {

			$aQuery['$and'][1][$sKey]['$exists'] = FALSE;
			$aSet = ['$set' => [$sKey => $aOption['init']]];
			$aResult = $this->_coll()
				->findAndModify($aQuery, $aSet, $aField, $aFindOption + ['upsert' => TRUE]);
			if ($aResult && isset($aResult[$sKey])) {
				return $aResult[$sKey];
			}

		} catch(\MongoDuplicateKeyException $e) {

			// 这其实是同一个语句执行两遍
			//
			// 也就是虽然都不满足（_id = ? and 不存在key）这个条件，
			// 但是也分“没有 document”和“有 document 但没有指定 key”两种情况
			//
			// 上面的 upsert = ture 是试探第一种情况，这里是试探第二种情况

			$aResult = $this->_coll()
				->findAndModify($aQuery, $aSet, $aField, $aFindOption);
			if ($aResult && isset($aResult[$sKey])) {
				return $aResult[$sKey];
			}
		}

		$aQuery = [static::$_mKey => $this->_mID];
		$aResult = $this->_coll()
			->findAndModify($aQuery, $aUpdate, $aField, $aFindOption);

		if (!$aResult || isset($aResult[$sKey])) {

			// 除了在这几条语句执行的过程中有别的连接
			// 执行了创建并删除 document 的操作，
			//
			// 不然我实在想象不出还有什么理由会没法生成自增id

			throw TangoException('can not update , unknown reason');
		}
		return $aResult[$sKey];
	}

	protected static function _checkKey($sKey) {
		if ($sKey === '_id') {
			return FALSE;
		}
		if ($sKey === static::$_mKey) {
			return FALSE;
		}
		if (strpos($sKey, '.') !== FALSE) {
			return FALSE;
		}
		if (substr($sKey, 0, 1) === '$') {
			return FALSE;
		}
		return TRUE;
	}
}
