<?php
namespace Tango\Drive;

use Tango\Core\TangoException;

abstract class DBEnumBase {

	static private $_sDB = '';
	static private $_sDBTable = '';

	static private $_sKeyID = 'id';
	static private $_lKeySearch = ['game', 'region'];

	static protected $_sKeyHash = '';
	static protected $_sHashAlgo = 'sha1'; // http://php.net/manual/en/function.hash-algos.php

	static private $_lPool = [];
	static private $_lPoolForName = [];
	static private $_lPoolForSort = [];
	static protected $_iPoolMax = 1000;

	static private $_bPreLoad = TRUE; // 预读，第一次加载的时候是否 SELECT LIMIT 1000

	static private $_iPoolNum = 0;
	static private $_bPoolFull = FALSE;

	static private $_iGet = 0; // 查询次数
	static private $_iDB = 0;  // 访问数据库次数

	protected static function _getDB() {
		if (!static::$_sDB || !static::$_sDBTable) {
			throw new TangoException('DB config empty');
		}
		return DB::getInstance(static::$_sDB);
	}

	public function getById($iID) {

		$iID = intval($iID);
		if ($iID < 1) {
			throw new TangoException('id error');
		}

		if (array_key_exists($iID, static::$_lPool)) {
			$aReturn = static::$_lPoolForName[$iID];
			if (count($aReturn) === 1) {
				return current($aReturn);
			}
			return $aReturn;
		}

		static::_checkPreload();

		static::_checkPoolFull();

		$sQuery = sprintf(
			'SELECT %s FROM %s WHERE %s = %d',
			implode(', ', static::$_lKeySearch),
			static::$_sDBTable,
			static::$_sKeyID,
			$iID
		);

		$oDB = static::_getDB();
		$aRow = $oDB->getRow($sQuery);
		if (!$aRow) {
			static::$_iPoolNum--;
			return FALSE;
		}

		$sHash = static::_hash($aRow);

		static::$_lPool[$iID] = $sHash;
		static::$_lPoolForName[$iID] = $aRow;
		if (static::$_lPoolForSort) {
			static::$_lPoolForSort[$iID] = TRUE;
		}

		if (count($aRow) === 1) {
			return current($aRow);
		}
		return $aRow;
	}

	protected static function _hash($aSearch) {
		return hash(static::$_sHashAlgo, serialize($aSearch), TRUE);
	}

	public function get($aSearch) {

		if (!is_array($aSearch)) {
			if (count(static::$_lKeySearch) !== 1) {
				throw new TangoException('input error');
			}
			$aSearch = [current(static::$_lKeySearch) => $aSearch];
		}

		if (array_keys($aSearch) !== static::$_lKeySearch) {
			// 因为 RowName 的顺序影响到 SELECT WHERE 的性能，
			// 所以顺序不对也会报错
			throw new TangoException('key not match');
		}

		$sHash = static::_hash($aSearch);

		// 变量缓存池，如果命中则不走数据库
		$iID = array_search($sHash, static::$_lPool, TRUE);
		if ($iID) {
			if (static::$_lPoolForSort) {
				unset(static::$_lPoolForSort[$iID]);
				static::$_lPoolForSort[$iID] = TRUE;
			}
			return (int)$iID;
		}

		static::_checkPreload();

		// 其实放到数据库操作之后更容易理解，
		// 但是针对新取出的 key 作为特例又得多写两行，所以放到前面了就
		static::_checkPoolFull();

		// 清理结束

		if (static::$_sKeyHash) {

			$sQuerySelect = sprintf(
				'SELECT %s FROM %s WHERE %s = ?',
				static::$_sKeyID,
				static::$_sDBTable,
				static::$_sKeyHash
			);

			$aValueSelect = [$sHash];

		} else {

			$lQuery = array_map(function ($sKey) {
				return $sKey.' = ?';
			}, static::$_lKeySearch);

			$sQuerySelect = sprintf(
				'SELECT %s FROM %s WHERE %s',
				static::$_sKeyID,
				static::$_sDBTable,
				implode(' AND ', $lQuery)
			);

			$aValueSelect = array_values($aSearch);
		}

		$oDB = static::_getDB();

		// SELECT/INSERT/SELECT 的顺序，正常情况下之会走前两步，
		// 如果有并发的情况有极小概率会走到第三步
		$iID = $oDB->getSingle($sQuerySelect, $aValueSelect);
		if (!$iID) {

			if (static::$_sKeyHash) {

				$lQuery = array_map(function ($sKey) {
					return $sKey.' = ?';
				}, array_merge([static::$_sKeyHash], static::$_lKeySearch));

				$sQueryInsert = sprintf(
					'INSERT IGNORE INTO %s SET %s',
					static::$_sDBTable,
					implode(', ', $lQuery)
				);

				$aValueInsert = array_merge([$sHash], array_values($aSearch));

			} else {

				$sQueryInsert = sprintf(
					'INSERT IGNORE INTO %s SET %s',
					static::$_sDBTable,
					implode(', ', $lQuery)
				);

				$aValueInsert = $aValueSelect;
			}

			$iID = $oDB->getInsertID($sQueryInsert, $aValueInsert);
			if (!$iID) {

				$iID = $oDB->getSingle($sQuerySelect, $aValueSelect);
				if (!$iID) {
					// 即使 INSERT 也无法 SELECT 的奇怪情况
					throw new TangoException('db error');
				}
			}
		}

		static::$_lPool[$iID] = $sHash;
		static::$_lPoolForName[$iID] = $aSearch;
		if (static::$_lPoolForSort) {
			static::$_lPoolForSort[$iID] = TRUE;
		}

		return (int)$iID;
	}

	// protected _checkPreload() {{{
	/**
	 * 预读，如果变量缓存池是空的，第一次从数据库取的时候，
	 * 一次性取 _iPoolMax 条（默认1000），以减少 mysql 请求数
	 *
	 * @static
	 * @access protected
	 * @return void
	 */
	protected static function _checkPreload() {

		if (!static::$_bPreLoad) {
			return FALSE;
		}

		static::$_bPreLoad = FALSE;

		if (static::$_sKeyHash) {
			static::_preloadWithHash();
		} else {
			static::_preload();
		}

		return TRUE;
	}
	// }}}

	// protected _checkPoolFull() {{{
	/**
	 * 如变量缓存池满，踢掉用到的最少的那
	 *
	 * @static
	 * @access protected
	 * @return void
	 */
	protected static function _checkPoolFull() {

		if (static::$_iPoolNum <= static::$_iPoolMax) {
			static::$_iPoolNum++;
			return FALSE;
		}

		if (!static::$_lPoolForSort) {
			$lKey = array_keys(static::$_lPool);
			rsort($lKey);
			static::$_lPoolForSort = array_fill_keys($lKey, TRUE);
		}
		$iOut = key(static::$_lPoolForSort);
		unset(static::$_lPool[$iOut]);
		unset(static::$_lPoolForName[$iOut]);
		unset(static::$_lPoolForSort[$iOut]);

		return TRUE;
	}
	// }}}

	protected static function _preload() {

		$lRowName = array_merge([static::$_sKeyID], static::$_lKeySearch);

		$sQuery = sprintf(
			'SELECT %s FROM %s LIMIT %d',
			implode(', ', $lRowName),
			static::$_sDBTable,
			static::$_iPoolMax
		);

		$oDB = static::_getDB();
		$lTmp = $oDB->getAll($sQuery, [], FALSE);
		if (!$lTmp) {
			return FALSE;
		}

		foreach ($lTmp as $aRow) {
			$iID = array_shift($aRow);
			$sHashRow = static::_hash($aRow);
			static::$_lPool[$iID] = $sHashRow;
			static::$_lPoolForName[$iID] = $aRow;
		}

		static::$_iPoolNum = count($lTmp);
	}

	protected static function _preloadWithHash() {

		$lRowName = array_merge([static::$_sKeyID, static::$_sKeyHash], static::$_lKeySearch);

		$sQuery = sprintf(
			'SELECT %s FROM %s LIMIT %d',
			implode(', ', $lRowName),
			static::$_sDBTable,
			static::$_iPoolMax
		);

		$oDB = static::_getDB();

		try {
			static::$_lPool = $oDB->getAll($sQuery, [], TRUE);
			// var_dump(array_map('bin2hex', static::$_lPool));
		} catch (TangoException $e) {
			throw new TangoException(
				static::$_sDB.'.'.static::$_sDBTable.' '
				.'preload failed, maybe RowName not match '
				. '(' . static::$_sKeyID . ', ' . static::$_sKeyHash . ')'
				.$sQuery
			);
		}

		foreach (static::$_lPool as $aRow) {
			$iID = array_shift($aRow);
			unset($aRow[static::$_sKeyHash]);
			static::$_lPoolForName[$iID] = $aRow;
		}
	}
}
