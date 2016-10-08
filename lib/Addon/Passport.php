<?php
namespace Tango\Addon;

use Tango\Core\TangoException;

class Passport {

	public static function id(int $iUser = -1) {

		if ($iUser < 0) {
			$iUser = Session::id();
		}

		if ($iUser < 1) {
			throw new TangoException('user id error: ' . $iUser);
		}

		$sQuery = 'SELECT * FROM user WHERE user_id = ' . $iUser;

		$oDB = \Tango\Drive\DB::getInstance('passport');
		return $oDB->getRow($sQuery) ?: [];
	}

	public static function create() {

		$sQuery = 'INSERT INTO user '
			. 'SET ts_create = ' . $_SERVER['REQUEST_TIME'];

		$oDB = \Tango\Drive\DB::getInstance('passport');
		return $oDB->getInsertID($sQuery);
	}
}
