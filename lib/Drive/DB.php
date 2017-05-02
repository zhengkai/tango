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
use Tango\Core\Log;

Config::setFileDefault('db', dirname(__DIR__) . '/Config/db.php');

/**
 * MySQL 操作类
 *
 * 主要特性有：
 * 智能化处理返回的数组
 * 减少连接数
 * 只在第一次做操作时开始连接
 * 自动重连
 * 简化常见操作
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class DB {

	/** 数据库alias */
	protected $_sName;

	/** 对于不同的 MySQL 字段做不同方式的处理 */
	protected $_lColumnNeedConvert;

	/** 保存不同的连接单例 */
	protected static $_lInstance = [];

	/** 连接信息 */
	protected $_aConfig = [];

	/**
	 * 原始 PDO 类
	 * @var \PDO
	 */
	protected $_oPDO;

	/**
	 * 如果定义了对照关系，会在表不存在的时候按指定表的结构来创建新表
	 * @see setAutoCreateTable
	 */
	protected $_aAutoCreateTable;

	/** 前一次的 PDO 报错 */
	protected $_iErrorLast;

	/** 一次 prepare 后可以多次 execute，这里保存那个 prepare 内容 */
	protected $_sPrevPrepareQuery;

	/** 一次 prepare 后可以多次 execute，这里保存那个 prepare 内容 */
	protected $_oPrevPrepare;

	/** 是否记 log */
	protected static $_bLog = TRUE;

	/** 对于不同的 MySQL 字段做不同方式的处理 */
	public static $lTypeNeedConvert = [];

	/**
	 * 开启 log
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function setLogOn() {
		static::$_bLog = TRUE;
	}

	/**
	 * 关闭 log
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function setLogOff() {
		static::$_bLog = FALSE;
	}

	/**
	 * 如果下一条是 INSERT 语句，这个函数设置可以保证 INSERT 时如果表不存在，
	 * 会自动创建表（基于已存在的表结构复制）
	 *
	 * @param string $sSource 原始表名
	 * @param string $sTarget 要复制的目标表名
	 * @access public
	 * @return void
	 */
	public function setAutoCreateTable($sSource, $sTarget) {
		$this->_aAutoCreateTable = [
			'source' => $sSource,
			'target' => $sTarget,
		];
	}

	/**
	 * 创建一个连接单例（相同的库只创建一个连接）
	 *
	 * @param string $sName 数据库alias
	 * @param boolean $bReset 是否重置连接
	 * @static
	 * @access public
	 * @return DB
	 * @throws TangoException
	 */
	public static function getInstance($sName, $bReset = FALSE) {

		if ($bReset) {
			static::$_lInstance[$sName] = NULL;
		}

		if (!$oDB =& static::$_lInstance[$sName]) {

			$aConfig = Config::get('db');
			$aServer =& $aConfig['server'][$sName];
			if (!is_array($aServer)) {
				throw new TangoException('unknown server "' . $sName . '"');
			}

			$aServer += $aConfig['default'];
			$aServer += [
				'dbname' => $sName, // if dbname is empty, set as name
				'debug' => FALSE,
			];

			$oDB = new self($aServer, $sName);
		}
		return $oDB;
	}

	/**
	 * 返回原始 PDO 类，方便直接操作
	 *
	 * http://php.net/manual/en/class.pdo.php
	 *
	 * @access public
	 * @return \PDO
	 */
	public function pdo() {
		return $this->_oPDO;
	}

	/**
	 * __construct
	 *
	 * 不应该直接 new，应通过 getInstance() 来获得
	 *
	 * @param array $aServer
	 * @param string $sName
	 * @access public
	 * @return DB
	 */
	public function __construct($aServer, $sName) {

		$this->_sName = $sName;
		$this->_bDebug = (bool)$aServer['debug'];

		$this->_aConfig = [
			'dsn' => sprintf(
				'mysql:%s;dbname=%s;charset=utf8mb4',
				$aServer['dsn'],
				$aServer['dbname']
			),
			'user' => $aServer['user'],
			'password' => $aServer['password'],
			'option' => [
				\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
				\PDO::ATTR_PERSISTENT => FALSE,
				\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
				\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => TRUE,
				\PDO::ATTR_EMULATE_PREPARES => boolval($aServer['pdo_prepare'] ?? TRUE),
			],
		];
	}

	/**
	 * 连接 MySQL 数据库
	 *
	 * @access protected
	 * @return boolean
	 * @throws TangoException
	 */
	protected function _connect(): bool {

		if ($this->_oPDO) {
			return FALSE;
		}

		$sName = $this->_sName;
		$sDSN = $this->_aConfig['dsn'];
		$this->_sPrevPrepareQuery = NULL;
		$this->_oPrevPrepare = NULL;

		try {

			$this->_oPDO = new \PDO(
				$this->_aConfig['dsn'],
				$this->_aConfig['user'],
				$this->_aConfig['password'],
				$this->_aConfig['option']
			);

		} catch (\Throwable $e) {

			$sError = 'MySQL Connect fail, Database "' . $sName . '"' . "\n"
				. $e->getMessage() . "\n"
				. 'DSN ' . $sDSN;
			throw new TangoException($sError, 2);
		}
		return TRUE;
	}

	/**
	 * 实际发请求
	 *
	 * 因为可能有异常（重连/表不存在等情况）需要再重试一次，所以包了一下
	 *
	 * @param string $sQuery
	 * @param array $aParam
	 * @param bool $bExec
	 * @access protected
	 * @return mixed
	 */
	protected function _queryTry(string $sQuery, array $aParam = [], bool $bExec) {

		if ($aParam) {

			if ($this->_sPrevPrepareQuery === $sQuery) {
				$oResult = $this->_oPrevPrepare;
			} else {
				$this->_sPrevPrepareQuery = $sQuery;
				$aOption = [];
				if (key($aParam) !== 0) {
					$aOption[\PDO::ATTR_CURSOR] = \PDO::CURSOR_FWDONLY;
				}
				$oResult = $this->_oPDO->prepare($sQuery, $aOption);
				$this->_oPrevPrepare = $oResult;
			}

			@$oResult->execute($aParam);
			return $bExec ? $oResult->rowCount() : $oResult;
		}

		return $bExec ? @$this->_oPDO->exec($sQuery) : @$this->_oPDO->query($sQuery);
	}

	/**
	 * 处理可以挽救的异常
	 *
	 * 返回值 true 表示重新执行 query
	 *
	 * @param array $aError 执行 query 后的报错信息
	 * @param string $sQuery
	 * @param array $aParam
	 * @access protected
	 * @throws DBException
	 * @return bool
	 */
	protected function _parseException(string $sErrorCode) {

		// 42S02 table doesn't exist
		if ($sErrorCode === '42S02') {
			if (!$this->_aAutoCreateTable) {
				return FALSE;
			}
			$this->_connect();
			$this->cloneTableStructure(
				$this->_aAutoCreateTable['source'],
				$this->_aAutoCreateTable['target']
			);
			$this->_aAutoCreateTable = NULL;
			return TRUE;
		}

		// HY000 lost connect (MySQL server has gone away)
		if ($sErrorCode === 'HY000') {
			$this->_oPDO = NULL;
			$this->_connect();
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * 转换变量类型 确定要转换的字段
	 *
	 * @param \PDOStatement $oResult
	 * @access protected
	 * @return void
	 */
	protected function _ColumnConvertScan(\PDOStatement $oResult) {

		$iCount = $oResult->columnCount();
		$lConvert = [];

		foreach (range(0, $iCount - 1) as $iColumn) {

			$aMeta = $oResult->getColumnMeta($iColumn);
			$aMeta['native_type'] = ($sTmp =& $aMeta['native_type']) ?: 'UNKNOWN';

			$fnConvert = FALSE;
			foreach (static::$lTypeNeedConvert as $iKey => $fnCheck) {
				if ($fnConvert = $fnCheck($aMeta)) {
					break;
				}
			}

			if ($fnConvert) {
				$lConvert[$aMeta['name']] = $fnConvert;
			}
		}

		$this->_lColumnNeedConvert = $lConvert;
	}

	/**
	 * 转换变量类型 执行转换
	 *
	 * @param array $aRow
	 * @access protected
	 * @return array
	 */
	protected function _ColumnConvertDo($aRow) {

		foreach ($this->_lColumnNeedConvert as $sKey => $fnConvert) {
			$aRow[$sKey] = $fnConvert($aRow[$sKey]);
		}

		return $aRow;
	}

	/**
	 * query 操作（SELECT/SHOW 等）
	 *
	 * @param string $sQuery
	 * @param array $aParam
	 * @access public
	 * @return mixed
	 */
	public function query($sQuery, array $aParam = []) {
		return $this->_query($sQuery, $aParam, FALSE);
	}

	/**
	 * exec 操作（INSERT、UPDATE、DELETE 等）
	 *
	 * @param string $sQuery
	 * @param array $aParam
	 * @access public
	 * @return mixed
	 */
	public function exec($sQuery, array $aParam = []) {
		return $this->_query($sQuery, $aParam, TRUE);
	}

	/**
	 * 实际执行 exec/query 操作
	 *
	 * @param string $sQuery
	 * @param array $aParam
	 * @param $sType
	 * @return mixed
	 * @throws TangoException
	 */
	protected function _query(string $sQuery, array $aParam = [], bool $bExec) {

		if (empty($sQuery)) {
			throw new TangoException('empty $sQuery', 3);
		}

		$this->_connect();
		$this->_iErrorLast = NULL;

		try {
			return $this->_queryTry($sQuery, $aParam, $bExec);
		} catch(\PDOException $e) {
			if (!$this->_parseException($e->getCode())) {
				throw $e;
			}
		}
		return $this->_queryTry($sQuery, $aParam, $bExec);
	}

	/**
	 * 对于有 auto increment 字段的 INSERT 的语句，返回其自增值
	 *
	 * @param mixed $sQuery
	 * @param array $aParam
	 * @access public
	 * @return integer
	 */
	public function getInsertID(string $sQuery, array $aParam = []) {
		if (!$this->_query($sQuery, $aParam, TRUE)) {
			return FALSE;
		}
		return (int)$this->_oPDO->lastInsertId();
	}

	/**
	 * auto increment id generator
	 *
	 * @param string $sTable
	 * @access public
	 * @return integer

	CREATE TABLE IF NOT EXISTS `id_gen` (
		`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		PRIMARY KEY (`id`)
	) ENGINE=MyISAM DEFAULT CHARSET=binary AUTO_INCREMENT=1;

	 */
	public function genAI($sTable) {
		$sTable = '`' . addslashes($sTable) . '`';
		$sQuery = 'INSERT INTO ' . $sTable . ' () VALUES ()';
		$iID = $this->getInsertID($sQuery);
		if ($iID && ($iID % 1000 == 0)) {
			$sQuery = 'DELETE FROM ' . $sTable;
			$this->exec($sQuery);
		}
		return $iID;
	}

	/**
	 * 最常用的方法，获取所有记录，返回为一维或二维数组
	 *
	 * @param string $sQuery
	 * @param array $aParam
	 * @param boolean $bByKey
	 * @access public
	 * @return array
	 */
	public function getAll($sQuery, array $aParam = [], $bByKey = TRUE) {

		$oResult = $this->_query($sQuery, $aParam, FALSE);

		$aData = $oResult->fetchAll();
		if (empty($aData)) {
			return $aData;
		}

		static::_ColumnConvertScan($oResult);

		$iColumnCount = $oResult->columnCount();
		if ($bByKey && $iColumnCount == 1) {
			$bByKey = FALSE;
		}
		$aKey = [];

		$bPeelArray = ($iColumnCount == ($bByKey ? 2 : 1));
		foreach ($aData as $iRowKey => $aRow) {

			$aRow = static::_ColumnConvertDo($aRow);

			// 取第一个字段为 key，替代原来的顺序数字
			if ($bByKey) {
				$aKey[$iRowKey] = $bPeelArray ? array_shift($aRow) : current($aRow);
			}

			// 如果只有一列，则不再需要原来的 array 套了
			if ($bPeelArray) {
				$aRow = current($aRow);
			}
			$aData[$iRowKey] = $aRow;
		}

		if ($bByKey) {
			$aData = array_combine($aKey, $aData);
		}

		return $aData;
	}

	/**
	 * 只取第一行
	 * @param $sQuery
	 * @param array $aParam
	 * @return mixed
	 * @throws TangoException
	 */
	public function getRow($sQuery, array $aParam = []) {

		$oResult = $this->_query($sQuery, $aParam, FALSE);

		$aRow = $oResult->fetch();

		if ($aRow) {
			static::_ColumnConvertScan($oResult);
			$aRow = static::_ColumnConvertDo($aRow);
		}

		$oResult->closeCursor();

		return $aRow;
	}

	/**
	 * 只取第一行的第一个字段
	 * @param $sQuery
	 * @param array $aParam
	 * @return bool|mixed
	 */
	public function getSingle($sQuery, array $aParam = []) {
		if (!$aRow = $this->getRow($sQuery, $aParam)) {
			return FALSE;
		}
		return current($aRow);
	}

	/**
	 * @ignore
	 * @param $aParam
	 * @return array
	 */
	public function page(array $aParam) {

		$aReturn = [[], 0, 1];

		$lReturn =& $aReturn[0];
		$iCount =& $aReturn[1];
		$iPage =& $aReturn[2];

		$aParam += [
			'select' => '*',
			'table' => '',
			'where' => '',
			'order_asc' => FALSE,
			'order_by' => '',
			'page' => 1,
			'number_per_page' => 20,
		];

		$iPage = $aParam['page'];
		if ($iPage < 1) {
			$iPage = 1;
		}

		if ($aParam['where']) {
			$aParam['where'] = 'WHERE ' . $aParam['where'];
		}

		$sQuery = sprintf('SELECT count(*) FROM %s %s', $aParam['table'], $aParam['where']);
		$iCount = $this->getSingle($sQuery) ?: 0;

		if (!$iCount) {
			$iPage = 1;
			return $aReturn;
		}

		$sLimit = $aParam['number_per_page'];

		if ($iCount <= $aParam['number_per_page']) {

			$iPage = 1;

		} else {

			$iPageMax = (int)ceil($iCount / $aParam['number_per_page']);
			if ($iPage > $iPageMax) {
				$iPage = $iPageMax;
			}

			if ($iPage <= ceil($iPageMax / 2)) {
				$sLimit = (($iPage - 1) * $aParam['number_per_page']) . ', ' . $aParam['number_per_page'];
			} else {
				if (!$aParam['order_by']) {
					$aParam['order_by'] = 'NULL';
				}
				$aParam['order_asc'] = !$aParam['order_asc'];

				$bReverseResult = TRUE;

				$iFill = $iCount % $aParam['number_per_page'];
				if ($iPage == $iPageMax) {
					$sLimit = $iFill;
				} else {
					$sLimit = (($iPageMax - $iPage - 1) * $aParam['number_per_page'] + $iFill) . ', ' . $aParam['number_per_page'];
				}
			}
		}

		$sOrder = '';
		if ($aParam['order_by']) {
			$sOrder = 'ORDER BY ' . $aParam['order_by'] . ' '
				. ($aParam['order_asc'] ? 'ASC' : 'DESC');
		}

		$sQuery = sprintf(
			'SELECT %s FROM %s %s %s LIMIT %s',
			$aParam['select'],
			$aParam['table'],
			$aParam['where'],
			$sOrder,
			$sLimit
		);

		$lReturn = $this->getAll($sQuery, [], FALSE) ?: [];
		if (!empty($bReverseResult)) {
			$lReturn = array_reverse($lReturn);
		}

		return [$lReturn, $iCount, $iPage];
	}

	/**
	 * 复制表结构、创建新表
	 *
	 * @param mixed $sTableSource
	 * @param mixed $sTableTarget
	 * @access public
	 * @return int
	 */
	public function cloneTableStructure(string $sTableSource, string $sTableTarget) {

		if (!$sTableSource || !$sTableTarget) {
			throw new TangoException('empty table name');
		}

		$aRow = $this->getRow('SHOW CREATE TABLE `' . $sTableSource . '`');
		$s = $aRow['Create Table'];
		$s = preg_replace(
			'#CREATE TABLE `' . $sTableSource . '` \(#',
			'CREATE TABLE IF NOT EXISTS `' . $sTableTarget . '` (',
			$s
		);
		return (int)$this->_query($s, [], TRUE);
	}

	/**
	 * 修复表
	 *
	 * @param string $sTable
	 * @access public
	 * @return array
	 */
	public function repairTable(string $sTable) {
		if (!$sTable) {
			throw new TangoException('empty table name');
		}
		$sQuery = 'REPAIR TABLE `' . addslashes($sTable) . '`';
		return $this->exec($sQuery);
	}

	/**
	 * 优化表
	 *
	 * @param string $sTable
	 * @access public
	 * @return array
	 */
	public function optimizeTable(string $sTable) {
		if (!$sTable) {
			throw new TangoException('empty table name');
		}
		$sQuery = 'OPTIMIZE TABLE `' . addslashes($sTable) . '`';
		return $this->exec($sQuery);
	}

	/**
	 * 清空表
	 *
	 * @param string $sTable
	 * @access public
	 * @return array
	 */
	public function emptyTable(string $sTable) {
		if (!$sTable) {
			throw new TangoException('empty table name');
		}
		$sQuery = 'TRUNCATE TABLE `' . addslashes($sTable) . '`';
		return $this->exec($sQuery);
	}

	/**
	 * 返回当前库占用的索引
	 *
	 * @access public
	 * @return integer
	 */
	public function getIndexSize() {

		$sQuery = 'SHOW TABLE STATUS';
		$lTable = $this->getAll($sQuery);
		$lIndex = array_column($lTable, 'Index_length');
		$iIndex = array_sum($lIndex);

		return $iIndex;
	}
}

// 类的 static 数组在定义时无法包含匿名函数，只能使用曲线方法
DB::$lTypeNeedConvert = [
	function ($aMeta) {
		$lType = ['DATE', 'DATETIME', 'TIMESTAMP'];
		if (!in_array($aMeta['native_type'], $lType)) {
			return FALSE;
		}
		return 'strtotime';
	},
	function ($aMeta) {
		$lType = ['LONG', 'LONGLONG'];
		if (!in_array($aMeta['native_type'], $lType)) {
			return FALSE;
		}
		return 'intval';
	},
	function ($aMeta) {
		if (substr($aMeta['name'], 0, 3) !== 'is_') {
			return FALSE;
		}

		return function ($sValue) {
			return $sValue === 'Y';
		};
	}
];
