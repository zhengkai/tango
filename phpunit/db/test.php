<?php
use Tango\Drive\DB;

\Tango\Core\Config::setFile('db', __DIR__.'/config.php');

class DBTest extends PHPUnit_Framework_TestCase {

	protected $_i = 0;

	protected function _randStr() {
		$this->_i++;
		$s = hash('crc32', sprintf('%.12f', microtime(TRUE).$this->_i));
		return str_replace('=', '', base64_encode(hex2bin($s)));
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
			switch ($i % 3) {
				case 0:
					$oDB->exec('INSERT INTO pdo_test SET name = "'.addslashes($this->_randStr()).'"');
					break;
				case 1:
					$oDB->exec('INSERT INTO pdo_test SET name = ?', [$this->_randStr()]);
					break;
				case 2:
					$oDB->exec('INSERT INTO pdo_test SET name = :abc', ['abc' => $this->_randStr()]);
					break;
			}
		}

		$s = $this->_randStr();
		$iInsert = $oDB->getInsertID('INSERT INTO pdo_test SET name = "'.addslashes($s).'"');
		$i++;
		$this->assertSame($i, $iInsert);

		$aRow = $oDB->getRow('SELECT name FROM pdo_test WHERE test_id = '.$iInsert);
		$this->assertSame(['name' => $s], $aRow);

		$s = $this->_randStr();
		$iInsert = $oDB->getInsertID('INSERT INTO pdo_test SET name = ?', [$s]);
		$i++;
		$this->assertSame($i, $iInsert);

		$aRow = $oDB->getRow('SELECT name FROM pdo_test WHERE test_id = ?', [$iInsert]);
		$this->assertSame(['name' => $s], $aRow, 'getRow');

		$s = $this->_randStr();
		$iInsert = $oDB->getInsertID('INSERT INTO pdo_test SET name = :abc', ['abc' => $s]);
		$i++;
		$this->assertSame($i, $iInsert);

		$aRow = $oDB->getRow('SELECT name FROM pdo_test WHERE test_id = :abc', ['abc' => $iInsert]);
		$this->assertSame(['name' => $s], $aRow);
	}

    /**
	 * @depends testInsert
     */
	public function testGet() {
		$oDB = DB::getInstance('test');
		$r = $oDB->exec('TRUNCATE TABLE pdo_test');

		// 生成测试用数据
		$a = [];
		foreach (range(1, mt_rand(20, 30)) as $i) {
			$aRow = [
				'test_id' => $i,
				'is_ban' => (bool)mt_rand(0, 1),
				'name' => $this->_randStr(),
				'date_create' => (int)$_SERVER['REQUEST_TIME'],
			];
			$a[$i] = $aRow;
			$sQuery = 'INSERT INTO pdo_test SET is_ban = ?, name = ?, date_create = ?';
			$oDB->exec($sQuery, [$aRow['is_ban'] ? 'Y' : 'N', $aRow['name'], $aRow['date_create']]);
		}

		// 标准 getAll
		$r = $oDB->getAll('SELECT * FROM pdo_test');
		$this->assertSame($a, $r);

		// 变量类型
		$first = current($r);
		$this->assertTrue(is_string($first['name']));
		$this->assertTrue(is_bool($first['is_ban']));
		$this->assertTrue(is_int($first['date_create']));

		// 只取一个字段
		$t = [];
		foreach ($a as $aRow) {
			$t[] = $aRow['name'];
		}
		$r = $oDB->getAll('SELECT name FROM pdo_test');
		$this->assertSame($t, $r);

		// 只取两个字段
		$t = [];
		foreach ($a as $aRow) {
			$t[$aRow['test_id']] = $aRow['name'];
		}
		$r = $oDB->getAll('SELECT test_id, name FROM pdo_test');
		$this->assertSame($t, $r);

		// byKey 而 key 冲突
		$t = $a;
		$t = end($t);
		$t = [
			$t['date_create'] => $t['name'],
		];
		$r = $oDB->getAll('SELECT date_create, name FROM pdo_test ORDER BY test_id ASC');
		$this->assertSame($t, $r);

		// byKey = false
		$t = [];
		foreach ($a as $aRow) {
			$t[] = [
				'date_create' => $aRow['date_create'],
				'name' => $aRow['name'],
			];
		}
		$r = $oDB->getAll('SELECT date_create, name FROM pdo_test ORDER BY test_id ASC', [], FALSE);
		$this->assertSame($t, $r);

		// prepare
		$t = $a;
		array_shift($t);
		array_pop($t);
		shuffle($t);
		$t = current($t);

		$t = [
			$t['test_id'] => [
				'test_id' => $t['test_id'],
				'name' => $t['name'],
				'is_ban' => $t['is_ban'],
			],
		];

		$r = $oDB->getAll('SELECT test_id, name, is_ban FROM pdo_test WHERE test_id = ?', [key($t)]);
		$this->assertSame($t, $r);

		// getRow
		$t = [
			'test_id' => current($t)['test_id'],
			'name' => current($t)['name'],
		];
		$r = $oDB->getRow('SELECT test_id, name FROM pdo_test WHERE test_id = ?', [$t['test_id']]);
		$this->assertSame($t, $r);

		$r = $oDB->getRow('SELECT test_id, name FROM pdo_test WHERE test_id = '.$t['test_id']);
		$this->assertSame($t, $r);

		// getSingle

		$r = $oDB->getSingle('SELECT name, date_create FROM pdo_test WHERE test_id = ?', [$t['test_id']]);
		$this->assertSame($t['name'], $r);

		$r = $oDB->getSingle('SELECT name, date_create FROM pdo_test WHERE test_id = '.$t['test_id']);
		$this->assertSame($t['name'], $r);
	}
}
