<?php
require_once __DIR__.'/enumDebug.trait.php';

class TestB extends Tango\Drive\DBEnumBase {

	use enumDebug;

	static protected $_sDB = 'test';
	static protected $_sDBTable = 'enum_test_b';

	static protected $_sKeyID = 'uid';
	static protected $_lKeySearch = ['row_a', 'row_b', 'row_c'];

	static protected $_lPool = [];
	static protected $_lPoolForName = [];
	static protected $_lPoolForSort = [];
	static protected $_iPoolMax = 3;

	static protected $_bPreLoad = TRUE;
	static protected $_iPoolNum = 0;
	static protected $_bPoolFull = FALSE;
}
