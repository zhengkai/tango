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

class DBKV {

	private static $_lPool = [];
	private static $_sDB;
	private static $_sDBTable;
	private static $_sKey;

	protected static $_aDefault;

	protected $_aData;
	protected $_iVer;
	protected $_iID;

	public static function getInstance(int $iID) {
		$o =& $_lPool[$iID];
		if (!$o) {
			$o = new static($iID);
		}
		return $o;
	}

	public function __construct(int $iID) {
		if (!empty(static::$_lPool[$iID])) {
			throw new TangoException('use "getInstance", don\'t "new"');
		}
		$this->_iID = $iID;
	}

	public function update(string $sKey, $mVal) {

		foreach (range(1, 3) as $iTry) {

			$this->_init();
			$this->_aData[$sKey] = $mVal;

			if ($this->_iVer) {
				$sQuery = 'UPDATE ' . static::$_sDBTable . ' '
					. 'SET content = "' . addslashes(json_encode($this->_aData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '", '
					. 'date_update = ' . $_SERVER['REQUEST_TIME'] . ', '
					. 'ver = ' . ($this->_iVer + 1) . ' '
					. 'WHERE ver = ' . $this->_iVer;
			} else {
				$sQuery = 'INSERT IGNORE INTO ' . static::$_sDBTable . ' '
					. 'SET content = "' . addslashes(json_encode($this->_aData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '", '
					. 'date_update = ' . $_SERVER['REQUEST_TIME'] . ', '
					. 'ver = 1';
			}

			$oDB = DB::getInstance(static::$_sDB);
			if ($oDB->exec($sQuery)) {
				$this->_iVer++;
				return TRUE;
			}

			$aData = [];
		}

		throw new TangoException('update fail');
	}

	public function get() {
		$this->_init();
		return $this->_aData += static::$_aDefault;
	}

	protected function _getDirect() {
	}

	protected function _init() {

		if (is_array($this->_aData)) {
			return FALSE;
		}

		$sQuery = 'SELECT ver, content '
			. 'FROM ' . static::$_sDBTable . ' '
			. 'WHERE ' . static::$_sKey . ' = ' . $this->_iID;

		$oDB = DB::getInstance(static::$_sDB);
		list($this->_iVer, $this->_aData) = array_values($oDB->getRow($sQuery) ?: [0, '[]']);

		$this->_aData = json_decode($this->_aData, TRUE) + static::$_aDefault;

		return TRUE;
	}
}
