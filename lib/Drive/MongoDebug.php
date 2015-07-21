<?php
namespace Tango\Drive;

trait MongoDebug {

	public function hello() {
		echo "\n", 'config: ', $this->_sConfig, "\n";
		$o = $this->_conn();
		echo ' class: ', get_class($o), "\n";
	}

	public static function debugPool() {
		return [
			'conf' => self::$_lPoolConnConf,
			'conn' => self::$_lPoolConn,
		];
	}

	public static function debugConfig() {
		$aConfig = self::_getConfig();
		return [
			'full' => self::$_lConfig,
			'current' => $aConfig,
		];
	}

	public function debugConn() {
		return $this->_conn();
	}

	public function debugColl() {
		return $this->_coll();
	}

	public function debugGet() {
		$aResult = $this->_coll()->findOne([static::$_mKey => $this->_mID]);
		unset($aResult[static::$_mKey]);
		return $aResult;
	}

	public function debugSet(array $aData = []) {

		$sConfig = get_called_class();

		static::$_lPoolDataChange[$sConfig][$this->_mID] = $aData;
		static::$_lPoolData[$sConfig][$this->_mID] = $aData;
		$this->_coll()->remove([static::$_mKey => $this->_mID]);
		return $this->_coll()->update(
			[static::$_mKey => $this->_mID],
			$aData,
			['upsert' => TRUE]
		);
	}

	public function debugUpdate(array $aData = []) {
		return $this->_coll()->update(
			[static::$_mKey => $this->_mID],
			$aData
		);
	}

	public function debugClose() {

		$sConfig = get_called_class();

		unset(static::$_lPoolDataChange[$sConfig][$this->_mID]);
		unset(static::$_lPoolData[$sConfig][$this->_mID]);
	}

	public static function debugDiff(array $a, array $b) {
		return self::_getDiff($a, $b);
	}
}
