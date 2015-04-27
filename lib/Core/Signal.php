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
 * 对 pcntl_signal 的包装
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class Signal {

	/** 是否应该退出（收到过 kill 信号），应在循环里可以安全退出的地方不断检测该值 */
	public static $bExit = FALSE;

	/**
	 * 用来接收信号，看是否是退出信号
	 *
	 * @param mixed $sSignal
	 * @static
	 * @access public
	 * @return void
	 */
	public static function handler($iSignal) {

		if (self::$bExit) {
			return;
		}

		switch($iSignal) {
			case SIGTERM:
			case SIGKILL:
			case SIGINT:
				self::$bExit = TRUE;
				break;
			default:
				break;
		}
	}

	/**
	 * 设定触发函数
	 *
	 * @see Signal::handler
	 * @static
	 * @access public
	 * @return void
	 */
	public static function startListen() {

		// 想使用的话，必须跟 declare(ticks=1); 配套

		pcntl_signal(SIGTERM, [__CLASS__, 'handler']);
		pcntl_signal(SIGINT,  [__CLASS__, 'handler']);
	}
}
