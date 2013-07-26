<?php
require dirname(dirname(__DIR__)).'/vendor/autoload.php';

use Tango\Drive\DB;

\Tango\Core\Config::setFile('db', __DIR__.'/config.php');

class DBTest extends PHPUnit_Framework_TestCase {

	protected $_i = 0;

	protected function _randStr() {
		$this->_i++;
		return base64_encode(hex2bin(hash('crc32', sprintf('%.12f', microtime(TRUE).$this->_i))));
	}

	public function testShowTable() {

		$oDB = DB::getInstance('test');

		$r = $oDB->getAll('SHOW TABLES');

		$this->assertContains('pdo_test', $r);
	}

    /**
	 * @depends testShowTable
     */
	public function testInsert() {

		$oDB = DB::getInstance('test');

		$r = $oDB->exec('TRUNCATE TABLE pdo_test');

		$i = mt_rand(20, 30);

		foreach (range(1, $i) as $i) {
			if ($i % 2 == 0) {
				$oDB->exec('INSERT INTO pdo_test SET name = "'.$this->_randStr().'"');
			} else {
				$oDB->exec('INSERT INTO pdo_test SET name = ?', [$this->_randStr()]);
			}
		}

		$s = $this->_randStr();
		$iInsert = $oDB->getInsertID('INSERT INTO pdo_test SET name = "'.$s.'"');
		$i++;
		$this->assertEquals($i, $iInsert);

		$aRow = $oDB->getRow('SELECT name FROM pdo_test WHERE test_id = '.$iInsert);
		$this->assertEquals(['name' => $s], $aRow);

		$s = $this->_randStr();
		$iInsert = $oDB->getInsertID('INSERT INTO pdo_test SET name = ?', [$s]);
		$i++;
		$this->assertEquals($i, $iInsert);

		$aRow= $oDB->getRow('SELECT name FROM pdo_test WHERE test_id = ?', [$iInsert]);
		$this->assertEquals(['name' => $s], $aRow);
	}

}
