<?php
use Tango\Drive\DB;

require_once __DIR__.'/enum/TestA.class.php';
require_once __DIR__.'/enum/TestB.class.php';
require_once __DIR__.'/enum/TestC.class.php';
require_once __DIR__.'/enum/TestD.class.php';

class DBEnumTest extends PHPUnit\Framework\TestCase {

	protected static $_lChar;

	/**
	 * 测试用物料，打乱顺序的 a 到 z
	 */
	protected static function _getChar() {
		if (self::$_lChar) {
			return self::$_lChar;
		}
		$lChar = range('a', 'z');
		shuffle($lChar);
		self::$_lChar = $lChar;
		return $lChar;
	}

	protected static function _init($oClass) {

//		$oClass::debugReset();
		$aDump = $oClass::debugDump();

		$oDB = DB::getInstance($aDump['_sDB']);
		$oDB->emptyTable($aDump['_sDBTable']);

		return [$aDump, $oDB];
	}

	/**
	 * 最基本功能
	 */
	public function testA() {

		$oTest = new TestA();

		self::_init($oTest);

		$lChar = self::_getChar();

		$i = 0;
		foreach ($lChar as $sChar) {
			$i++;
			$iID = $oTest->get($sChar);
		}

		$i = 10;
		$iID = $oTest->get($lChar[$i - 1]);
		$this->assertSame($i, $iID);

		$i = 5;
		$aRow = $oTest->getById($i);
		$this->assertSame($aRow, $lChar[$i - 1]);
	}

	/**
	 * 多个 row 情况
	 *
	 * id RowName 不用默认值（id -> uid）
	 *
	 * @depends testA
	 */
	public function testB() {

		$oTest = new TestB();
		self::_init($oTest);

		$lChar = self::_getChar();

		$lTest = array_map(function ($sChar) {
			return [
				'row_a' => $sChar,
				'row_b' => mt_rand(1, 2147483647),
				'row_c' => str_repeat($sChar, mt_rand(10, 50)),
			];
		}, $lChar);

		$i = 0;
		foreach ($lTest as $aTest) {
			$i++;
			$iID = $oTest->get($aTest);
		}

		$i = 10;
		$aRow = $oTest->get($lTest[$i - 1]);
		$this->assertSame($i, $aRow);

		$i = 5;
		$aRow = $oTest->getById($i);
		$this->assertSame($aRow, $lTest[$i - 1]);
	}

	/**
	 * 以 hash 为唯一值的情况
	 *
	 * hash RowName 不用默认值（hash -> hash_a）
	 *
	 * @depends testB
	 */
	public function testC() {

		$oTest = new TestC();
		self::_init($oTest);

		$lChar = self::_getChar();

		$lTest = array_map(function ($sChar) {
			return [
				'row_a' => str_repeat($sChar, mt_rand(500, 5000)),
			];
		}, $lChar);

		$i = 0;
		foreach ($lTest as $aTest) {
			$i++;
			$iID = $oTest->get($aTest);
		}

		$i = 10;
		$aRow = $oTest->get($lTest[$i - 1]);
		$this->assertSame($i, $aRow);

		$i = 5;
		$aRow = $oTest->getById($i);
		$this->assertSame($aRow, current($lTest[$i - 1]));
	}

	/**
	 * 以 hash 为唯一值的情况
	 *
	 * 多个 row
	 * hash 方式为 md5
	 *
	 * @depends testC
	 */
	public function testD() {

		$oTest = new TestD();
		self::_init($oTest);

		$lChar = self::_getChar();

		$lTest = array_map(function ($sChar) {
			return [
				'row_a' => str_repeat($sChar, mt_rand(500, 5000)),
				'row_b' => mt_rand(1, 2147483647),
			];
		}, $lChar);

		$i = 0;
		foreach ($lTest as $aTest) {
			$i++;
			$iID = $oTest->get($aTest);
		}

		$i = 10;
		$aRow = $oTest->get($lTest[$i - 1]);
		$this->assertSame($i, $aRow);

		$i = 5;
		$aRow = $oTest->getById($i);
		$this->assertSame($aRow, $lTest[$i - 1]);
	}

	/**
	 * 循环测试 100 次 testPoolGet
	 */
	public function loopProvider() {
		return array_fill(0, 100, [1]);
	}

	/**
	 * 测试数组缓存 get
	 *
	 * 主要涉及排序，最久没访问过的 item 最早清出缓存池
	 * 白盒测试
	 *
	 * @depends testA
	 * @depends testB
	 * @depends testC
	 * @depends testD
	 * @dataProvider loopProvider
	 */
	public function testPoolGet($iUseless = 0) {

		$lChar = self::_getChar();

		$oTest = new TestA();
		$oTest->debugReset();

		$oTest->get(current($lChar));

		$aDump = $oTest->debugDump();

		$iLength = $aDump['_iPoolMax'];

		$lSort = array_fill_keys(array_keys($aDump['_lPool']), TRUE);

		$iCheck = 0;
		$iLoop = 50;
		$bCheckPool = FALSE;

		do {

			$iKey = mt_rand(1, count($lChar));

			$iCount = count($lSort);
			unset($lSort[$iKey]);
			if (count($lSort) == $iCount) { //
				$iCheck++;
				if ($iCheck > $iLength) {
					$iLoop--;
					$bCheckPool = TRUE;
				}
			}

			$lSort[$iKey] = TRUE;
			$lSort = array_slice($lSort, - $iLength, $iLength, TRUE);

			$iGet = $oTest->get($lChar[$iKey - 1]);
			$this->assertSame($iKey, $iGet);

			$aDump = $oTest->debugDump();

			$iCount = count($lSort);

			if ($bCheckPool) {

				$this->assertSame($lSort, $aDump['_lPoolForSort']);

			}

		} while ($iLoop > 0);
	}
}
