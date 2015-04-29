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

use Tango\Page\HTML;

/**
 * 页面输出
 *
 * 负责 Web 访问时的页面输出（HTML/JSON/etc）
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class Page {

	/** 当前的扩展名 */
	protected static $_aExt = FALSE;

	/** 可用的扩展名 */
	protected static $_lExt = [
		'html' => [
			'mime' => 'text/html',
		],
		'md' => [
			'mime' => 'text/html',
		],
		'json' => [
			'mime' => 'application/json',
		],
		'jsonp' => [
			'mime' => 'application/javascript',
		],
		'text' => [
			'mime' => 'text/plain',
		],
		'xml' => [
			'mime' => 'application/xml',
		],
	];

	/** 是否要做模板部分的处理 */
	protected static $_bParse = FALSE;

	/** 是否已经处理完模板 */
	protected static $_bWellDone = FALSE;

	/**
	 * 是否已经处理完模板
	 * @static
	 * @access public
	 * @return boolean
	 */
	public static function isWellDone() {
		return self::$_bWellDone;
	}

	/**
	 * 在必要时重置输出（如 www 页出现异常后重新渲染显示 error 500 页面），通常情况下不需要用到
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function reset() {
		self::$_bParse = FALSE;
	}

	/**
	 * 通用的错误页面
	 *
	 * @param string $sError 简短的错误信息
	 * @static
	 * @access public
	 * @return void
	 * @throws TangoException
	 */
	public static function error($sError) {
		if (self::$_bParse) {
			throw new TangoException('Page has been sent');
		}
		Tango::$T['error'] = $sError;
	}

	/**
	 * 调试页面（如仅限 127.0.0.1 访问的页面）对于不符合要求的请求显示 404，使外部无法确认调试页面是否是该地址
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function debugGate() {
		if (!Tango::isDebug()) {
			self::error('http404');
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * 设置扩展名（输出类型）
	 *
	 * @param string $sExt 在 self::$_lExt 里列举的那些 key（html/json 等）
	 * @param boolean $bTry 如果为 true，在失败的时候不报错
	 * @static
	 * @access public
	 * @return void
	 */
	public static function set($sExt, $bTry = FALSE) {
		if (self::$_aExt) {
			if (!$bTry) {
				trigger_error('ext exists');
			}
			return FALSE;
		}
		if (!isset(self::$_lExt[$sExt])) {
			if (!$bTry) {
				trigger_error('unknown ext "'.$sExt.'"');
			}
			return FALSE;
		}
		$aExt = [
			'ext' => $sExt,
			'ob' => TRUE,
		];
		$aExt += self::$_lExt[$sExt];
		self::$_aExt = $aExt;
		return TRUE;
	}

	/**
	 * 获取当前扩展名
	 *
	 * @static
	 * @access public
	 * @return array
	 */
	public static function get() {
		return self::$_aExt;
	}

	/**
	 * 不处理模板页
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function stopParse() {
		self::$_bParse = TRUE;
	}

	/**
	 * 页面跳转
	 *
	 * @param string $sURL 将要跳转的 URL
	 * @static
	 * @access public
	 * @return void
	 * @throws TangoException
	 */
	public static function jump($sURL) {
		if (self::$_bParse) {
			throw new TangoException('jump before parse');
		}
		self::$_bParse = TRUE;
		header('Location: ' . $sURL);
		exit;
	}

	/**
	 * 处理模板页
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function parse() {

		if (self::$_bParse) {
			return FALSE;
		}
		self::$_bParse = TRUE;

		Page::set('html', TRUE);

		$sExt = self::$_aExt['ext'];

		if ($sExt === 'html') {

			if ($aError = Tango::getStopError()) {
				Tango::$T['error'] = 'http500';
			}

			if (!empty(Tango::$T['error'])) {
				switch ((string)Tango::$T['error']) {
					case 'http500':
						HTML::setTpl('main', '/error/500');
						break;
					case 'http404':
						HTML::setTpl('main', '/error/404');
						break;
					default:
						HTML::setTpl('main', '/error/default');
						break;
				}
			}

			HTML::run();

			return TRUE;
		}

		$call = [__CLASS__, '_parse'.ucfirst($sExt)];
		if (is_callable($call)) {
			header('Content-Type: '.self::$_aExt['mime'].'; charset=utf-8');
			$call();
			return TRUE;
		} else {
			header('Content-Type: text/plain; charset=utf-8');
			echo "\n\t", 'Error: method "'.$sExt.'" incomplete', "\n";
			return FALSE;
		}
	}

	/**
	 * 如果扩展名是 txt 时的输出
	 *
	 * @static
	 * @access protected
	 * @return void
	 */
	protected static function _parseText() {
		Tango::$T += ['output' => ''];
		echo Tango::$T['output'];
		return TRUE;
	}

	/**
	 * 如果扩展名是 json 时的输出
	 *
	 * @static
	 * @access protected
	 * @return void
	 */
	protected static function _parseJson() {
		echo json_encode(Tango::$T, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		return TRUE;
	}
}
