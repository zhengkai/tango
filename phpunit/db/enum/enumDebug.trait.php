<?php
trait enumDebug {

	public static function debugReset($bDB = FALSE) {

		if ($bDB) {
			$oDB = static::_getDB();
			$oDB->emptyTable(self::$_sDBTable);
		}

		static::$_lPool = [];
		static::$_lPoolForName = [];
		static::$_lPoolForSort = [];

		static::$_bPreLoad = TRUE;
		static::$_iPoolNum = 0;
		static::$_bPoolFull = FALSE;
	}

	public static function debugDump() {

		return [
			'_sDB' => static::$_sDB,
			'_sDBTable' => static::$_sDBTable,

			'_sKeyID' => static::$_lPool,
			'_lKeySearch' => static::$_lKeySearch,

			'_sKeyHash' => static::$_sKeyHash,
			'_sHashAlgo' => static::$_sHashAlgo,

			'_lPool' => static::$_lPool,
			'_lPoolForName' => static::$_lPoolForName,
			'_lPoolForSort' => static::$_lPoolForSort,
			'_iPoolMax' => static::$_iPoolMax,

			'_bPreLoad' => static::$_bPreLoad,
			'_iPoolNum' => static::$_iPoolNum,
			'_bPoolFull' => static::$_bPoolFull,
		];
	}
}
