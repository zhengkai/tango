<?php
namespace Tango\Drive;

/*

example:

CREATE TABLE IF NOT EXISTS `country` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `short` char(2) NOT NULL,
  `full` char(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `short` (`short`)
) ENGINE=MyISAM DEFAULT CHARSET=ascii AUTO_INCREMENT=1;

 */

class DBEnum {

	protected $_oDB;
	protected $_sDBTable;

	protected $_iLimit = 1000;
	protected $_bMoreThanLimit = FALSE;
	protected $_bGetAll = FALSE;

	protected $_lPool = [];

	protected $_iCount = 0;

	public function __construct($sDBTable, $sDB) {
		$this->_oDB = DB::getInstance($sDB);
		$this->_sDBTable = $sDBTable;
	}

	public function getByKey($iKey) {
		$iKey = intval($iKey);
		if ($iKey < 1) {
			return FALSE;
		}
		if (array_key_exists($iKey, $this->_lPool)) {
			return $this->_lPool;
		}
		$sQuery = 'SELECT * FROM `'.$this->_sDBTable.'` WHERE id = '.$iKey;
		if (!$aRow = $this->_oDB->getRow($sQuery)) {
			return FALSE;
		}
		return $this->_lPool[$iKey] = array_values($aRow)[1];
	}

	public function get(array $lArg) {

		$sKey = (string)current($lArg);

		if (!$sKey) {
			return FALSE;
		}

		if (array_key_exists($sKey, $this->_lPool)) {
			return $this->_lPool[$sKey];
		}

		$lQuerySub = [];
		foreach ($lArg as $sRowName => $mVal) {
			$lQuerySub[] = '`'.$sRowName.'` = '.(is_int($mVal) ? $mVal : '"'.addslashes($mVal).'"');
		}

		$sQuery = 'INSERT IGNORE INTO `'.$this->_sDBTable.'` SET '.implode(', ', $lQuerySub);
		$iAI = $this->_oDB->getInsertID($sQuery);
		if (!$iAI) {
			$sQuery = 'SELECT id FROM `'.$this->_sDBTable.'` WHERE '.implode(' AND ', $lQuerySub);
			$iAI = $this->_oDB->getSingle($sQuery);
			if (!$iAI) {
				return FALSE;
			}
		}

		if (!$this->_bMoreThanLimit && $this->_iCount < $this->_iLimit) {
			$this->_iCount++;
			$this->_lPool[$sKey] = $iAI;
		} else {
			$this->_bMoreThanLimit = TRUE;
		}
		return $iAI;
	}

	public function getAll() {

		if ($this->_bGetAll) {
			return $this->_lPool;
		}
		$this->_bGetAll = TRUE;

		$iLimitOver = $this->_iLimit + 1;

		$sQuery = 'SELECT * FROM `'.$this->_sDBTable.'` LIMIT '.$iLimitOver;
		$this->_lPool = array_map(
			function ($aRow) {
				if (!is_array($aRow)) {
					return $aRow;
				}
				return array_values($aRow)[1];
			},
			$this->_oDB->getAll($sQuery) ?: []
		);
		if (!$this->_bMoreThanLimit && count($this->_lPool) == $iLimitOver) {
			$this->_bMoreThanLimit = TRUE;
		}

		return $this->_lPool;
	}

	public function isOverflow() {
		return $this->_bGetAll && $this->_bMoreThanLimit;
	}
}
