<?php
require_once __DIR__ . '/class/User.php';
require_once __DIR__ . '/class/UserAI.php';
require_once __DIR__ . '/class/IdGen.php';
require_once __DIR__ . '/class/Map.php';

class MongoBatchTest extends PHPUnit_Framework_TestCase {

	public function testConfig() {

		$aConfig = User::debugConfig()['current'];
		$this->assertEquals($aConfig, [
			'collection' => 'user',
			'debug' => FALSE,
			'db' => 'tango_phpunit_test',
			'capacity' => 10000,
			'max_capacity' => 30000,
			'pool' => [
				'127.0.0.1:12306',
				'127.0.0.1:12307',
				'127.0.0.1:12308',
			],
		]);

		$aConfig = UserAI::debugConfig()['current'];
		$this->assertEquals($aConfig, [
			'collection' => 'user_ai',
			'debug' => FALSE,
			'db' => 'tango_phpunit_test',
			'capacity' => 10000,
			'max_capacity' => 30000,
			'pool' => [
				'127.0.0.1:12306',
				'127.0.0.1:12307',
				'127.0.0.1:12308',
			],
		]);

		$aConfig = IdGen::debugConfig()['current'];
		$this->assertEquals($aConfig, [
			'collection' => 'id_gen',
			'debug' => FALSE,
			'db' => 'tango_phpunit_test',
			'capacity' => 0,
			'max_capacity' => 0,
			'pool' => [
				'127.0.0.1:12306',
			],
		]);

		$aConfig = Map::debugConfig()['current'];
		$this->assertEquals($aConfig, [
			'collection' => 'map',
			'debug' => FALSE,
			'db' => 'tango_phpunit_test',
			'capacity' => 10000,
			'max_capacity' => 30000,
			'pool' => [
				'127.0.0.1:12306',
				'127.0.0.1:12307',
				'127.0.0.1:12308',
			],
		]);
	}

	public function testInit() {

		$iUser = 10001;

		$o = new User($iUser);

		$o->debugColl()->drop();

		$o->get();
		$o->update(['a' => 'b']);
		$aResult = $o->save();

		$aCheck = [
			"ok" => 1,
			"nModified" => 0,
			"n" => 1,
			"err" => NULL,
			"errmsg" => NULL,
			"upserted" => $iUser,
			"updatedExisting" => FALSE,
		];

		$this->assertEquals($aResult, $aCheck);

		$o->update(['a' => 'c']);
		$aResult = $o->save();

		$aCheck = [
			"ok" => 1,
			"nModified" => 1,
			"n" => 1,
			"err" => NULL,
			"errmsg" => NULL,
			"updatedExisting" => TRUE,
		];

		$this->assertEquals($aResult, $aCheck);

	}

	public function testUpdate() {

		$aData = [
			'a' => 'b',
			'c' => [
				'b' => 2,
				'd' => 1,
			],
		];

		$aUpdate = [
			'c' => [
				'b' => 1,
				'c' => 1,
				'e' => [
					'f' => 1,
					'a' => 2,
				],
			],
		];

		$aResult = User::_update($aData, $aUpdate);

		$aAssert = [
			'a' => 'b',
			'c' => [
				'b' => 1,
				'd' => 1,
				'c' => 1,
				'e' => [
					'f' => 1,
					'a' => 2,
				],
			],
		];

		$this->assertSame($aResult, $aAssert);
	}

	public function testCheckKey() {

		$aUpdate = [
			'c' => [
				'b' => 1,
				'c' => 1,
				'e' => [
					'f' => 2,
					'f.c' => 1,
					'a' => 2,
				],
			],
		];

		$this->assertFalse(User::checkKey($aUpdate));

		unset($aUpdate['c']['e']['f.c']);

		$this->assertTrue(User::checkKey($aUpdate));
	}

	public function testArrayPath() {

		$lPath = ['a', 'b', 'c', 'd'];
		$a = User::arrayPath($lPath, 'yes rpg');

		$b = [
			'a' => [
				'b' => [
					'c' => [
						'd' => 'yes rpg',
					],
				],
			],
		];

		$this->assertSame($a, $b);
	}

	public function testDiffBase() {

		$a = [
			'd1' => [
				'a' => 1,
				'b' => 2,
				'c' => 3,
				'k_inc' => 1,
			],
			'z' => 'k',
			'abc' => 'def',
		];

		$b = [
			'abc' => 'def',
			'd1' => [
				'k_inc' => 10,
				'a' => 1,
				'c' => 2,
				'f' => 3,
			],
			'f' => 1.2,
		];

		$check = [
			'$set' => [
				'd1.f' => 3,
				'f' => 1.2,
				'd1.c' => 2,
			],
			'$unset' => [
				'z' => TRUE,
				'd1.b' => TRUE,
			],
			'$inc' => [
				'd1.k_inc' => 9,
			],
		];

		$f = microtime(TRUE);
		$diff = User::debugDiff($a, $b);
		/*
		$f = microtime(TRUE) - $f;
		echo "\n", sprintf('%.9f', $f), "\n";

		echo json($check);
		echo json($diff);
		 */

		$this->assertEquals($diff, $check);

		$o = new User(102);
		$o->debugSet($a);
		$o->debugUpdate($diff);

		$aResult = $o->debugGet();

		// echo json($aResult), "\n";

		$o->debugClose();

		$aGet = $o->get();
		$aGet += ['v' => FALSE];
		$this->assertSame($aGet['v'], 3);
	}

	public function testPool() {

		foreach ([10010, 5, 29999, 100, 10500, 9999, 21000] as $iUser) {
			$o = new User($iUser);

			$aConfig = $o->debugConfig();
			$sDB = $aConfig['current']['db'];

			$this->assertSame($sDB, 'tango_phpunit_test');

			$o->debugConn()->dropDB($sDB);

			$o->get();
			$o->save();
		}

		$l = User::debugPool();

		$iPoolCount = count($aConfig['current']['pool']);

		$this->assertSame($iPoolCount, count($l['conn']));

		array_map(function ($o) use ($iPoolCount, $sDB) {

			$this->assertSame($iPoolCount, count($o->getHosts()));
			$this->assertSame($iPoolCount, count($o->getConnections()));

			$lDB = array_column($o->listDBs()['databases'], 'name');

			$this->assertContains($sDB, $lDB);

		}, $l['conn']);

		$this->assertSame($l['conf'], [
			'127.0.0.1:12307',
			'127.0.0.1:12306',
			'127.0.0.1:12308',
		]);
	}
}
