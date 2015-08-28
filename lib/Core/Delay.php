<?php
/**
 * This file is part of the Tango Framework.
 *
 * (c) Zheng Kai <zhengkai@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tango\Core;

/**
 * 将不重要的事情移到脚本最后去做
 *
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class Delay {

	/** callback 池 */
	static $_lPool = [];

	/** 确保只运行一次 */
	static $_bRun = FALSE;

	static $_bHook= FALSE;

	/**
	 * 增加任务
	 *
	 * @param callable $func
	 * @static
	 * @access public
	 * @return void
	 */
	public static function add(callable $func) {
		self::$_lPool[] = $func;
		if (!self::$_bHook) {
			self::$_bHook = TRUE;
			Page::doDelay();
		}
	}

	/**
	 * 一次性执行所有任务
	 *
	 * 这个已经被 Page 类调用，通常不需要主动 Delay::run()
	 *
	 * @static
	 * @access public
	 * @return boolean
	 */
	public static function run() {

		if (self::$_bRun) {
			return FALSE;
		}
		self::$_bRun = TRUE;

		foreach (self::$_lPool as $func) {
			$func();
		}

		return TRUE;
	}
}
