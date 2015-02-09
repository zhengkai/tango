<?php
require_once __DIR__.'/enumDebug.trait.php';

class TestD extends Tango\Drive\DBEnumBase {

	use enumDebug;

	static protected $_sDB = 'test';
	static protected $_sDBTable = 'enum_test_d';

	static protected $_sKeyID = 'id';
	static protected $_lKeySearch = ['row_a', 'row_b'];

	static protected $_sKeyHash = 'hash';
	static protected $_sHashAlgo = 'md5';

	static protected $_lPool = [];
	static protected $_lPoolForName = [];
	static protected $_lPoolForSort = [];
	static protected $_iPoolMax = 3;

	static protected $_bPreLoad = TRUE;
	static protected $_iPoolNum = 0;
	static protected $_bPoolFull = FALSE;
}
