<?php
namespace Tango\Drive;

use Tango\Core\Config;
use Tango\Core\TangoException;

class DB extends \PDO {

	protected $_sName;
	protected $_bDebug;
	protected $_lColumnNeedConvert;

	static protected $_lInstance = [];
	protected $_aConfig = [];

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

	public function __construct($aServer, $sName) {

		$this->_sName = $sName;
		$this->_bDebug = (bool)$aServer['debug'];

		$this->_aConfig = [
			'dsn' => 'mysql:'.$aServer['dsn'].';dbname='.$aServer['dbname'].';charset=utf8',
			'user' => $aServer['user'],
			'password' => $aServer['password'],
			'option' => [
				\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
			]
		];

		$this->_connect();
	}

	protected function _connect() {

		$sName = $this->_sName;
		$sDSN = $this->_aConfig['dsn'];

		try {

			parent::__construct(
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
	protected function _connectSmart() {
		if ($this->errorInfo()[1] == 2006) { // "MySQL server has gone away"
			$this->_connect();
			return TRUE;
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

	public function query($sQuery, $aParam = []) {

		if (empty($sQuery)) {
			throw new TangoException('empty query');
		}

		do {
			if ($aParam) {
				$oResult = $this->prepare($sQuery);
				$oResult->execute($aParam);
			} else {
				$oResult = parent::query($sQuery);
			}
		} while ($this->_connectSmart());

		return $oResult;
	}

	public function pdoQuery() {

		$aArg = func_get_args();

		if (empty($aArg[0])) {
			throw new TangoException('empty query');
		}

		do {
			$oResult = call_user_func_array(['parent', 'query'], $aArg);
		} while ($this->_connectSmart());

		return $oResult;
	}

	public function exec($sQuery, $aParam = []) {

		if (empty($sQuery)) {
			throw new TangoException('empty query');
		}

		do {
			if ($aParam) {
				$oResult = $this->prepare($sQuery);
				$iAffected = $oResult->execute($aParam);
			} else {
				$iAffected = parent::exec($sQuery);
			}
		} while ($this->_connectSmart());

		return $iAffected;
	}

	protected function _errorLog($sQuery, $fTime) {

		if (strlen($sQuery) < 10) {
			throw new TangoException('MySQL Query Error: '.$sQuery, 2);
		}

		$aInfo = [
			'server' => $this->_sName,
			'query' => strlen($sQuery) > 1000 ? mb_substr($sQuery, 0, 1000) : $sQuery,
			'time' => sprintf('%.06f', $fTime * 1000),
		];

		if ($this->errorCode() > 0) {

			$aError = $this->errorInfo();
			$aInfo['error'] = [
				'code' => $aError[1],
				'message' => $aError[2],
			];

			$sMsg = 'MySQL Query Error '.$aError[1].":\n"
				.$aError[2];

			throw new TangoException($sMsg, 2);
		}

		// DB::addDebugLog($aInfo);
	}

	public function getInsertID($sQuery) {
		if (!$this->exec($sQuery)) {
			return FALSE;
		}
		return $this->lastInsertId();
	}

	public function getAll($sQuery, $aParam = [], $bByKey = TRUE) {

		if ($aParam) {
			$oResult = $this->prepare($sQuery);
			$oResult->execute($aParam);
		} else {
			$oResult = $this->query($sQuery);
		}

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

	// 只取第一行
	public function getRow($sQuery, $aParam = []) {

		if ($aParam) {
			$oResult = $this->prepare($sQuery);
			$oResult->execute($aParam);
		} else {
			$oResult = $this->query($sQuery);
		}

		$aRow = $oResult->fetch();

		if (!empty($aRow)) {
			self::_ColumnConvertScan($oResult);
			$aRow = self::_ColumnConvertDo($aRow);
		}

		return $aRow;
	}

	// 只取第一行的第一个字段
	public function getSingle($sQuery) {

		if (!$aRow = $this->getRow($sQuery)) {
			return FALSE;
		}

		$aRow = current($aRow);

		return $aRow;
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
