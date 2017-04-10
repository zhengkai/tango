<?php
require __DIR__ . '/lib/Page.php';
require __DIR__ . '/lib/HTML.php';

Page::start('/abc/def.php');

class HtmlTest extends PHPUnit\Framework\TestCase {

	/**
	 * 检查设置 tpl 相对/绝对路径是否符合预期
	 *
	 * @access public
	 * @return void
	 */
	public function testTpl() {

		// t1

		HTML::debugReset();
		HTML::setTpl('t1');

		$this->assertSame(
			HTML::getTpl('/w1.php'),
			'/basedir/tpl/t1.php',
			't1'
		);

		// t2

		HTML::debugReset();
		HTML::setTpl('t2');

		$this->assertSame(
			HTML::getTpl('/xyz/w2.php'),
			'/basedir/tpl/xyz/t2.php',
			't2'
		);

		// t3

		HTML::debugReset();

		$this->assertSame(
			HTML::getTpl('/xyz/w3.php'),
			'/basedir/tpl/xyz/w3.php',
			't3'
		);

		// t4

		HTML::debugReset();
		HTML::setTplType('type1');

		$this->assertSame(
			HTML::getTpl('/xyz/w1.php'),
			'/basedir/tpl/xyz/w1.type1.php',
			't4'
		);

		// t5

		HTML::debugReset();
		HTML::setTpl('t5');
		HTML::setTplType('type5');

		$this->assertSame(
			HTML::getTpl('/w5.php'),
			'/basedir/tpl/t5.type5.php',
			't5'
		);

		// t6

		HTML::debugReset();
		HTML::setTpl('t6');
		HTML::setTplType('type6');

		$this->assertSame(
			HTML::getTpl('/xyz/w6.php'),
			'/basedir/tpl/xyz/t6.type6.php',
			't6'
		);
	}
}
