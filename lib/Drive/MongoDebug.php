<?php
namespace Tango\Drive;

trait MongoDebug {

	public function hello() {
		echo 'config: ' . static::$_sConfig, "\n";
		$o = $this->_conn();
		echo ' class: ' . get_class($o), "\n";
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
		static::$_lPoolDataChange[static::$_sConfig][$this->_mID] = $aData;
		static::$_lPoolData[static::$_sConfig][$this->_mID] = $aData;
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
		unset(static::$_lPoolDataChange[static::$_sConfig][$this->_mID]);
		unset(static::$_lPoolData[static::$_sConfig][$this->_mID]);
	}
}
