<?php
namespace Tango\Drive;

use Tango\Core\Config;
use Tango\Core\TangoException;

class DB {

	protected $_sName;
	protected $_bDebug;
	protected $_lColumnNeedConvert;

	static protected $_lInstance = [];
	protected $_aConfig = [];

	protected $_oPDO = FALSE;

	static public $lTypeNeedConvert = [];

	public static function getInstance($sName) {

		if (!$oDB =& self::$_lInstance[$sName]) {

			$aConfig = Config::get('db');
			$aServer =& $aConfig['server'][$sName];
			if (!is_array($aServer)) {
				throw new TangoException('unknown server "'.$sName.'"');
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

	public function pdo() {
		return $this->_oPDO;
	}

	public function __construct($aServer, $sName) {

		$this->_sName = $sName;
		$this->_bDebug = (bool)$aServer['debug'];

		$this->_aConfig = [
			'dsn' => 'mysql:'.$aServer['dsn'].';dbname='.$aServer['dbname'].';charset=utf8',
			'user' => $aServer['user'],
			'password' => $aServer['password'],
			'option' => [
				\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
			],
		];

		$this->_connect();
	}

	protected function _connect() {

		$sName = $this->_sName;
		$sDSN = $this->_aConfig['dsn'];

		try {

			$this->_oPDO = new \PDO(
				$this->_aConfig['dsn'],
				$this->_aConfig['user'],
				$this->_aConfig['password'],
				$this->_aConfig['option']
			);

		} catch (\Exception $e) {

			$sError = 'MySQL Connect fail, Database "'.$sName.'"'."\n"
				.$e->getMessage()."\n"
				.'DSN '.$sDSN;
			throw new TangoException($sError, 2);
		}
	}

	/*
	 * 自动重连
	 */
	protected function _connectSmart($aError) {
		if ($aError[1] == 2006) {
			$this->_connect();
			return TRUE;
		} else if ($aError[1]) {
			throw new TangoException('PDO '.$aError[1].': '.$aError[2]);
		}
		return FALSE;
	}

	protected function _ColumnConvertScan($oResult) {

		// 转换变量类型 确定要转换的字段
		$iCount = $oResult->columnCount();
		$lConvert = [];

		foreach (range(0, $iCount - 1) as $iColumn) {

			$aMeta = $oResult->getColumnMeta($iColumn);
			$aMeta['native_type'] = ($sTmp =& $aMeta['native_type']) ?: 'UNKNOWN';

			$fnConvert = FALSE;
			foreach (self::$lTypeNeedConvert as $iKey => $fnCheck) {
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

	protected function _ColumnConvertDo($aRow) {

		// 转换变量类型 执行转换
		foreach ($this->_lColumnNeedConvert as $sKey => $fnConvert) {
			$aRow[$sKey] = $fnConvert($aRow[$sKey]);
		}

		return $aRow;
	}

	public function query($sQuery, array $aParam = []) {
		return $this->_query($sQuery, $aParam, 'query');
	}

	public function exec($sQuery, array $aParam = []) {
		return $this->_query($sQuery, $aParam, 'exec');
	}

	// public _query(sQuery,array aParam=[],sType) {{{
	/**
	 * _query
	 *
	 * @param mixed $sQuery
	 * @param array $aParam
	 * @param string $sType 'query' or 'exec'
	 * @access public
	 * @return mixed
	 */
	public function _query($sQuery, array $aParam = [], $sType) {

		\Tango\Core\Log::debug('query', $sQuery);

		if (empty($sQuery)) {
			throw new TangoException('empty $sQuery', 3);
		}

		do {
			if ($aParam) {

				$aOption = [];
				if (key($aParam) !== 0) {
					$aOption[\PDO::ATTR_CURSOR] = \PDO::CURSOR_FWDONLY;
				}

				$oResult = $this->_oPDO->prepare($sQuery, $aOption);
				$oResult->execute($aParam);
				$aError = $oResult->errorInfo();
				if ($sType === 'exec') {
					$iAffected = $oResult->rowCount();
				}
			} else {
				if ($sType === 'exec') {
					$iAffected = $this->_oPDO->exec($sQuery);
				} else {
					$oResult = $this->_oPDO->query($sQuery);
				}
				$aError = $this->_oPDO->errorInfo();
			}
		} while ($this->_connectSmart($aError));

		return $sType === 'exec' ? $iAffected : $oResult ;
	}
	// }}}

	public function getInsertID($sQuery, array $aParam = []) {
		if (!$this->_query($sQuery, $aParam, 'exec')) {
			return FALSE;
		}
		return (int)$this->_oPDO->lastInsertId();
	}

	// public genAI(sTable) {{{
	/**
	 * auto increment id generator
	 *
	 * @param mixed $sTable
	 * @access public
	 * @return integer

CREATE TABLE IF NOT EXISTS `id_gen` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=ucs2 AUTO_INCREMENT=1 ;

	 */
	public function genAI($sTable) {
		$sTable = '`'.addslashes($sTable).'`';
		$sQuery = 'INSERT INTO '.$sTable.' SET id = NULL';
		$iID = $this->getInsertID($sQuery);
		// if ($iID && !mt_rand(0, 10000)) {
		if ($iID) {
			$sQuery = 'DELETE FROM '.$sTable;
			$this->exec($sQuery);
		}
		return $iID;
	}
	// }}}

	public function getAll($sQuery, array $aParam = [], $bByKey = TRUE) {

		$oResult = $this->_query($sQuery, $aParam, 'query');

		$aData = $oResult->fetchAll();
		if (empty($aData)) {
			return $aData;
		}

		self::_ColumnConvertScan($oResult);

		$iColumnCount = $oResult->columnCount();
		if ($bByKey && $iColumnCount == 1) {
			$bByKey = FALSE;
		}
		if ($bByKey) {
			$aKey = [];
		}
		$bPeelArray = ($iColumnCount == ($bByKey ? 2 : 1));
		foreach ($aData as $iRowKey => $aRow) {

			$aRow = self::_ColumnConvertDo($aRow);

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

	// public getRow(sQuery,array aParam=[]) {{{
	/**
	 * 只取第一行
	 *
	 * @param mixed $sQuery
	 * @param array $aParam
	 * @access public
	 * @return void
	 */
	public function getRow($sQuery, array $aParam = []) {

		$oResult = $this->_query($sQuery, $aParam, 'query');

		$aRow = $oResult->fetch();

		if ($aRow) {
			self::_ColumnConvertScan($oResult);
			$aRow = self::_ColumnConvertDo($aRow);
		}

		return $aRow;
	}
	// }}}

	// public getSingle(sQuery,array aParam=[]) {{{
	/**
	 *
	 * 只取第一行的第一个字段
	 *
	 * @param mixed $sQuery
	 * @param array $aParam
	 * @access public
	 * @return void
	 */
	public function getSingle($sQuery, array $aParam = []) {
		if (!$aRow = $this->getRow($sQuery, $aParam)) {
			return FALSE;
		}
		return current($aRow);
	}
	// }}}

	public function page(array $aParam) {

		$aReturn = [[], 0, 1];

		$lReturn =& $aReturn[0];
		$iCount  =& $aReturn[1];
		$iPage   =& $aReturn[2];

		$aParam += [
			'select' => '*',
			'table' => '',
			'where' => '',
			'order_asc' => FALSE,
			'order_by' =>  '',
			'page' => 1,
			'number_per_page' => 20,
		];

		$iPage = $aParam['page'];
		if ($iPage < 1) {
			$iPage = 1;
		}

		if ($aParam['where']) {
			$aParam['where'] = 'WHERE '.$aParam['where'];
		}

		$sQuery = sprintf('SELECT count(*) FROM %s %s', $aParam['table'], $aParam['where']);
		$iCount = $this->getSingle($sQuery) ?: 0;

		if (!$iCount) {
			$iPage = 1;
			return $aReturn;
		}

		$sOrder = '';

		if ($iCount <= $aParam['number_per_page']) {

			if ($aParam['order_by']) {
				$sOrder = 'ORDER BY '.$aParam['order_by'].' ';
				$sOrder .= $aParam['order_asc'] ? 'ASC' : 'DESC';
			}

			$sQuery = sprintf(
				'SELECT %s FROM %s %s %s LIMIT %d',
				$aParam['select'],
				$aParam['table'],
				$aParam['where'],
				$sOrder,
				$aParam['number_per_page']
			);
			$iPage = 1;

		} else {

			$iPageMax = ceil($iCount / $aParam['number_per_page']);
			if ($iPageMax)
			$bReverse = FALSE;
			$sLimit = (($iPage - 1) * $aParam['number_per_page']).', '.$aParam['number_per_page'];

			if ($iPage > $iPageMax) {
				$iPage = $iPageMax;
			}

			/*
			if ($iPage > ceil($iPageMax / 2)) {
				$bReverse = TRUE;
				$aParam['order_asc'] = !$aParam['order_asc'];

				$iMod = $iCount % $iPage * $aParam['number_per_page'];
			}
			 */

			if ($aParam['order_by']) {
				$sOrder = 'ORDER BY '.$aParam['order_by'].' ';
				$sOrder .= $aParam['order_asc'] ? 'ASC' : 'DESC';
			}

			$sQuery = sprintf(
				'SELECT %s FROM %s %s %s LIMIT %s',
				$aParam['select'],
				$aParam['table'],
				$aParam['where'],
				$sOrder,
				$sLimit
			);
		}

		$lReturn = $this->getAll($sQuery, [], FALSE) ?: [];

		return [$lReturn, $iCount, $iPage];
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

		return function($sValue) {
			return $sValue === 'Y';
		};
	}
];
