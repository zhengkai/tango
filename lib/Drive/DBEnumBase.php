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
 * DBEnumBase 枚举类型
 *
 * 对数量不确定的内容，利用 MySQL 自增 id 编号，并通过 MRU（最近最频繁使用）算法缓存于 PHP 变量内存中
 * 典型场合如域名、文件名等
 *
 * @abstract
 * @package
 * @author Zheng Kai <zhengkai@gmail.com>
 */
abstract class DBEnumBase {

	/**
	 * 数据库连接名（是给 \Tango\Drive\DB 用的库名alias，不是实际的 MySQL db 名）
	 *
	 * @see \Tango\Drive\DB
	 */
	private static $_sDB = '';

	/** 数据库表名 */
	private static $_sDBTable = '';

	/** id 的字段名 */
	private static $_sKeyID = 'id';

	/** 内容的字段名 */
	private static $_lKeySearch = ['game', 'region'];

	/**
	 * 如果需要 hash，定义 hash 的字段名
	 *
	 * 注意有些内容是定长的（可以用 int 或者 char 装下的，如用户名），而有些内容是过长（如 char(200) 才能装下、或者合法 URL 可以到 4KB），建议做 hash
	 */
	protected static $_sKeyHash = '';

	/**
	 * hash 方式
	 *
	 * @see http://php.net/manual/en/function.hash-algos.php
	 */
	protected static $_sHashAlgo = 'sha1'; //

	/** 只记内容 id 的变量缓存池 */
	private static $_lPool = [];

	/** 变量缓存池 */
	private static $_lPoolForName = [];

	/** MRU 计数器 */
	private static $_lPoolForSort = [];

	/** 变量缓存池数量 */
	protected static $_iPoolMax = 1000;

	/** 预读，第一次加载的时候是否 SELECT LIMIT 1000 */
	private static $_bPreLoad = TRUE;

	/** 变量缓存池数量（节省点 count(self::$_lPool) 的效率） */
	private static $_iPoolNum = 0;

	/** 变量缓存池是否已满 */
	private static $_bPoolFull = FALSE;

	/** 查询次数 */
	private static $_iGet = 0;

	/** 访问数据库次数 */
	private static $_iDB = 0;

	/**
	 * 获取数据库连接
	 *
	 * @static
	 * @access protected
	 * @return void
	 */
	protected static function _getDB() {
		if (!static::$_sDB || !static::$_sDBTable) {
			throw new TangoException('DB config empty');
		}
		return DB::getInstance(static::$_sDB);
	}

	/**
	 * 根据 id 获取指定内容
	 *
	 * @param mixed $iID
	 * @access public
	 * @return void
	 */
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

	/**
	 * 按照 static::$_sKeyHash 指定的方式做 hash
	 *
	 * @param mixed $aSearch
	 * @static
	 * @access protected
	 * @return void
	 */
	protected static function _hash($aSearch) {
		return hash(static::$_sHashAlgo, serialize($aSearch), TRUE);
	}

	/**
	 * 给内容分配 id
	 *
	 * @param mixed $aSearch
	 * @access public
	 * @return void
	 */
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

	/**
	 * 如变量缓存池满，踢掉用到的最少的那
	 *
	 * @static
	 * @access protected
	 * @return boolean
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

	/**
	 * 实际预读操作
	 *
	 * @static
	 * @access protected
	 * @return void
	 */
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

	/**
	 * 实际预读操作（带 hash）
	 *
	 * @static
	 * @access protected
	 * @return void
	 */
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
