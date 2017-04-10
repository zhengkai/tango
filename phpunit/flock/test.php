#! /usr/bin/env php
<?php

class FlockTest extends PHPUnit\Framework\TestCase {

	/**
	 * 额外挂一个 sh 脚本并发执行 6 次 php，然后检查输出的结果是否符合格式
	 */
	public function test() {
		exec(__DIR__ . '/background_run.sh 2>&1', $l);
		$l = array_map(function ($s) {
			return trim($s);
		}, $l);

		sort($l);

		$this->assertCount(18, $l);

		$lStart = array_slice($l, 12, 6);
		$lEnd   = array_slice($l, 0, 6);

		$lStart = array_map(['self', '_substr'], $lStart);
		$lEnd   = array_map(['self', '_substr'], $lEnd);

		$lEnd = array_unique($lEnd);
		$this->assertSame($lStart, $lEnd);

		$lSlice = array_slice($l, 6, 4);
		$lCheck = array_fill(0, 4, 'free');
		$this->assertSame($lSlice, $lCheck);

		$lSlice = array_slice($l, 10, 2);
		$lCheck = array_fill(0, 2, 'locked');
		$this->assertSame($lSlice, $lCheck);

		foreach (range(1, 3) as $i) {
			$sFile = sprintf(__DIR__ . '/lockname_test.%d.lock', $i);
			$this->assertTrue(file_exists($sFile));
		}

		return FALSE;
	}

	static protected function _substr($s) {
		return (int)substr($s, 10);
	}
}
