<?php
require_once __DIR__ . '/class/UserAI.php';

class MongoAITest extends PHPUnit_Framework_TestCase {

	public function testGen() {

		$oA = new UserAI(1);
		$oA->debugColl()->drop();

		$oB = new UserAI(20001);
		$oB->debugColl()->drop();

		// test User 1

		$iFoo = $oA->gen('foo', ['init' => 2, 'step' => 3]);
		$iBar = $oA->gen('bar');

		$this->assertEquals($iFoo, 2);
		$this->assertEquals($iBar, 1);

		$iBar = $oA->gen('bar');
		$iFoo = $oA->gen('foo', ['init' => 2, 'step' => 3]);

		$this->assertEquals($iFoo, 5);
		$this->assertEquals($iBar, 2);

		// test User 20001

		$iFoo = $oB->gen('foo');
		$iBar = $oB->gen('bar', ['init' => 2, 'step' => 3]);

		$this->assertEquals($iFoo, 1);
		$this->assertEquals($iBar, 2);

		$iBar = $oB->gen('bar', ['init' => 2, 'step' => 3]);
		$iFoo = $oB->gen('foo');

		$this->assertEquals($iFoo, 2);
		$this->assertEquals($iBar, 5);
	}

    /**
	 * @depends testGen
     */
	public function testConn() {

		$l = UserAI::debugPool();

		$this->assertEquals(2, count($l['conn']));
	}
}
