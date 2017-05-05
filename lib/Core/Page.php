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

Config::setFileDefault('page', dirname(__DIR__) . '/Config/page.php');

/**
 * 页面输出
 *
 * 负责 Web 访问时的页面输出（HTML/JSON/etc）
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class Page {

	/** www 传给 tpl 的变量，经过 HTML 过滤 */
	public static $T = [];

	/** www 传给 tpl 的变量，没有 HTML 过滤 */
	public static $D = [];

	/** 经过 \Tango\Core\Filter 过滤的输入参数（原 $_GET/$_POST） */
	public static $IN = [];

	protected static $_bFail = FALSE;

	protected static $_sBaseDir = '';

	protected static $_sWww = '';
	protected static $_bStopWww = FALSE;

	protected static $_sURI;
	protected static $_sTpl;

	protected static $_bDelay; // 是否执行 Delay::run()

	protected static $_oLayout;
	protected static $_sLayout;

	protected static $_oThrow;

	protected static $_fTimeWww;
	protected static $_fTimeTpl;

	protected static $_sContentType = 'html';

	protected static $_lHookPreWww = [];

	protected static $_iStep = self::STEP_INIT;
	public const STEP_INIT    = 100;
	public const STEP_WWW     = 200;
	public const STEP_CONTENT = 300;
	public const STEP_TPL     = 400;
	public const STEP_LAYOUT  = 500;
	public const STEP_END     = 999;

	public const CONTENT_TYPE_LIST = [
		'html' => 'text/html',
		'txt' => 'text/plain',
		'json' => 'application/json',
		'jsonp' => 'application/javascript',
		'js' => 'application/javascript',
		'xml' => 'application/xml',
		'css' => 'text/css',
	];

	public static function cacheForever(): bool {

		if (
			!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])
			|| !empty($_SERVER['HTTP_IF_NONE_MATCH'])
		) {
			http_response_code(304);
			self::stopWww();
			return TRUE;
		}

		header('ETag: "cache-forever"');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', time()));

		self::setExpireMax();

		return FALSE;
	}

	public static function etag(string $sETag): bool {

		$sETag = '"' . $sETag . '"';
		$sETagCheck = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
		if (
			$sETagCheck === $sETag
			|| $sETagCheck === 'W/' . $sETag
		) {
			http_response_code(304);
			self::stopWww();
			return TRUE;
		}

		header('ETag: ' . $sETag);

		return FALSE;
	}

	public static function setExpireMax() {
		header('Expires: Thu, 31 Dec 2037 23:55:55 GMT');
		header('Cache-Control: max-age=315360000');
	}

	public static function getBaseDir(): string {
		return self::$_sBaseDir;
	}

	protected static function _checkFileSafe(string $sPath, string $sSubDir = '') {
		$sBaseDir = self::$_sBaseDir . '/';
		if ($sSubDir) {
			$sBaseDir .= rtrim($sSubDir, '/') . '/';
		}
		if (strpos($sPath, $sBaseDir) === 0) {
			return TRUE;
		}
		self::$_oThrow = new \Exception($sSubDir . ' file ' . $sFile. ' unsafe');
		return FALSE;
	}

	public static function setContentType(string $sType) {

		if (self::$_iStep != self::STEP_WWW) {
			throw new Exception('only can be use in www page, step = '. self::$_iStep);
		}
		if (!array_key_exists($sType, self::CONTENT_TYPE_LIST)) {
			throw new Exception('unknown content type "'. $sType .'" / '.implode(array_keys(self::CONTENT_TYPE_LIST)));
		}
		static::$_sContentType = $sType;
	}

	protected static function sendContentTypeHeader(string $sContentType = '') {

		static $_bSend = FALSE;
		if ($_bSend) {
			self::$_iStep = 'end';
			throw new TangoException('duplicate set');
		}
		$_bSend = TRUE;

		$sOut = self::CONTENT_TYPE_LIST[$sContentType ?: self::$_sContentType];

		header('Content-Type: ' . $sOut);
	}

	public static function setLayout(string $sLayout) {
		self::$_sLayout = $sLayout;
	}

	public static function getConfig() {
		return Config::get('page');
	}

	protected static function _register(bool $bForce = FALSE) {
		static $_bInit;
		if ($bForce || !$_bInit) {
			$_bInit = TRUE;
			register_shutdown_function([get_called_class(), 'shutdown']);
		}
	}

	public static function start(string $sURI): void {

		ob_start();

		self::_register();

		if (!self::$_sBaseDir) {
			self::$_sBaseDir = dirname(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file']);
		}

		self::$_sURI = $sURI;

		self::_www();

		self::_content();

		self::_tpl();

		self::_layout();
	}

	protected static function _fallbackTplDebug(): string {
		return dirname(__DIR__) . '/Page/tpl/500_debug.php';
	}

	protected static function _fallbackTplNotFound(): string {
		return dirname(__DIR__) . '/Page/tpl/404_notfound.php';
	}

	public static function doDelay() {
		if (PHP_SAPI === 'fpm-fcgi') {
			self::$_bDelay = TRUE;
		}
	}

	protected static function _www(): void {

		self::$_iStep = self::STEP_WWW;

		if (strpos(self::$_sURI, '..') !== FALSE) {
			self::$_oThrow = new \Exception('Looks like an attack: ' . self::$_sURI);
			return;
		}

		$sFile = self::$_sBaseDir . '/www' . self::$_sURI;

		if (!self::_checkFileSafe($sFile, 'www')) {
			return;
		}

		$T =& self::$T;
		$D =& self::$D;
		$_IN =& self::$IN;

		if (self::_hookPreWww()) {
			return;
		}

		self::$_fTimeWww = microtime(TRUE);
		try {
			(function () use ($sFile, &$T, &$D, &$_IN) {
				require $sFile;
			})();
		} catch(\Throwable $e) {
			self::$_oThrow = $e;
		}
	}

	protected static function _content(): void {

		self::$_iStep = self::STEP_CONTENT;
		self::$_fTimeWww = microtime(TRUE) - self::$_fTimeWww;

		if (self::$_oThrow) {
			ob_clean();
			return;
		}

		if (ob_get_length()) {
			self::$_iStep = self::STEP_END;
			if (static::$_sContentType !== 'html') {
				self::sendContentTypeHeader();
			}
			return;
		}

		if (self::$_iStep === self::STEP_END) {
			return;
		}

		if (static::$_sContentType === 'html') {
			self::$T = HTML::escape(self::$T);
			return;
		}

		if (in_array(static::$_sContentType, ['json', 'jsonp'])) {
			$sCallback = '';
			if (static::$_sContentType === 'jsonp') {
				$sCallback = self::$IN['callback'] ?? $_GET['callback'] ?? '';
				if (!is_string($sCallback)) {
					$sCallback = '';
				} else if (!preg_match('#^[_$a-zA-Z\x{A0}-\x{FFFF}][_$a-zA-Z0-9\x{A0}-\x{FFFF}]*$#u', $sCallback)) {
					$sCallback = '';
				}
			}

			self::$_iStep = self::STEP_END;
			self::sendContentTypeHeader();

			if (strlen($sCallback)) {
				echo $sCallback . '(';
			}
			echo json_encode(self::$T, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			if (strlen($sCallback)) {
				echo ')';
			}
			return;
		}
	}

	protected static function _tpl(): void {

		if (self::$_iStep === self::STEP_END) {
			return;
		}

		self::$_iStep = self::STEP_TPL;

		$sFile = self::$_sTpl ?: HTML::getTpl(self::$_sURI);
		self::_checkFileSafe($sFile, 'tpl');

		if (self::$_oThrow) {
			$bDebugTpl = TRUE;
			$sFile = static::_fallbackTplDebug();
		}

		$T =& self::$T;
		$D =& self::$D;
		$_IN =& self::$IN;

		$oThrow = self::$_oThrow;
		self::$_oThrow = NULL;

		self::$_fTimeTpl = microtime(TRUE);

		if (is_readable($sFile)) {
			try {
				(function () use ($sFile, $T, $D, $_IN, $oThrow) {
					require $sFile;
				})();
			} catch(\Throwable $e) {
			}
		} else {
			$e = new \Exception('no tpl ' . $sFile);
		}

		if (!empty($e) && empty($bDebugTpl)) {
			ob_clean();
			self::$_oThrow = $e;
			self::$_iStep = self::STEP_CONTENT;
			exit;
		}
	}

	public static function addHookPreWww(callable $cb): void {
		static::$_lHookPreWww[] = $cb;
	}

	protected static function _hookPreWww(): bool {
		foreach (static::$_lHookPreWww as $cb) {
			if ($cb(self::$_sURI)) {
				return true;
			}
		}
		return false;
	}

	public static function getTimeWww(): string {
		return self::$_fTimeWww;
	}

	public static function getTimeTpl(): string {
		return self::$_fTimeTpl;
	}

	protected static function _layout() {

		self::$_fTimeTpl = microtime(TRUE) - self::$_fTimeTpl;
		$sBody = ob_get_clean();

		self::$_iStep = self::STEP_LAYOUT;

		if (self::$_oThrow) {
			throw self::$_oThrow;
		}

		$oLayout = static::initLayout();
		if (self::$_sLayout) {
			$oLayout->setLayout(self::$_sLayout);
		}

		$oLayout->setBody($sBody);
		$oLayout->run();
	}

	protected static function _missingPage($sURI, $sFile) {
		self::$_iStep = self::STEP_END;
		self::setContentType('txt');
		echo 'ERROR: missing uri ' . $sURI;
	}

	protected static function _notfoundPage(string $sTpl = ''): string {

		if (self::$_bFail) {
			return '';
		}
		self::$_bFail = TRUE;

		return $sTpl ?: dirname(__DIR__) . '/Page/tpl/404_notfound.php';
	}

	public static function getThrow(): Throwable {
		return self::$_oThrow;
	}

	public static function shutdown() {

		if (self::$_iStep === self::STEP_END) {
			return;
		}

		self::_register();

		switch (self::$_iStep) {

		case self::STEP_WWW:

			self::_content();

		case self::STEP_CONTENT:

			self::_tpl();

		case self::STEP_TPL:

			self::_layout();
		}

		/*
		if (self::$_bDelay) {
			fastcgi_finish_request();
			Delay::run();
		}
		 */
	}

	public static function initLayout() {

		// 延迟加载，如果没有 HTML 输出，可以避免加载 Layout 类的开销

		if (!self::$_oLayout) {
			self::$_oLayout = new \Tango\Page\Layout();
		}
		return self::$_oLayout;
	}

	public static function jump($sURL) {
		self::$_iStep = self::STEP_END;
		ob_clean();
		if (preg_match('#^/#', $sURL)) {
			$sURL = Config::get('page')['site_url'] . ltrim($sURL, '/');
		}
		header('Location: ' . $sURL);
		exit;
	}

	public static function stopWww() {
		if (self::$_bStopWww) {
			return FALSE;
		}
		self::$_bStopWww = TRUE;

		if (self::$_iStep === self::STEP_WWW) {
			self::$_iStep = 'end';
		} else if (self::$_iStep !== 'init') {
			throw new \Exception('only can be use in www page, now step ' . self::$_iStep);
		}
	}
}
