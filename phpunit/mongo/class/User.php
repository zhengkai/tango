<?php
class User extends Tango\Drive\Mongo {

	use Tango\Drive\MongoDebug;

	protected static $_sKeyType = 'int';
	protected static $_mKey = '_id';

	protected static $_lDiff = [];

	protected static $_lIncKey = [
		'd1.k_inc',
	];

	protected function _init() {

		$v = [];
		$v['time_create'] = date('Y-m-d H:i:s');
		return $v;
	}

	protected function _format(array $a) {

		$v =& $a['v'];
		if ($v < 3) {
			$v = 3;
		}
		return $a;
	}
}
