<?php
class PageTest extends PHPUnit_Framework_TestCase {

	protected function _makeTest(string $sName) {

		$sDir = __DIR__ . '/web';

		$sScript = $sDir . '/test.php ' . escapeshellarg($sName);
		$sExpect = $sDir . '/expect/' . $sName . '.html';
		$sOutput = $sDir . '/output/' . $sName . '.html';

		$sCmd = $sScript . ' 2>&1 > ' . $sOutput;
		shell_exec($sCmd);

		return [
			$sExpect,
			$sOutput,
		];
	}

	public function testPage() {

		foreach ([
			'index',
			'base',
			'www_echo',
			'www_error',
			'www_exception',
			'tpl_miss',
			'tpl_error',
			'tpl_exception',
			'type_json',
			'type_jsonp',
			'type_txt',
			'html_meta',

		] as $sTest) {

			list($sExpect, $sOutput) = self::_makeTest($sTest);
			$this->assertFileEquals($sExpect, $sOutput, $sTest);
		}
	}
}