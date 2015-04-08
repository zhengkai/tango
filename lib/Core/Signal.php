<?php
namespace Tango\Core;

/**
 * 对 pcntl_signal 的包装
 */
class Signal {

	public static $bExit = FALSE;

	public static function handler($sSignal) {

		if (self::$bExit) {
			return;
		}

		switch($sSignal) {
			case SIGTERM:
			case SIGKILL:
			case SIGINT:
				self::$bExit = TRUE;
				break;
			default:
				break;
		}
	}

	public static function startListen() {

		// 想使用的话，必须跟 declare(ticks=1); 配套

		pcntl_signal(SIGTERM, [__CLASS__, 'handler']);
		pcntl_signal(SIGINT,  [__CLASS__, 'handler']);
	}
}
