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
	protected static $_T = [];

	/** www 传给 tpl 的变量，没有 HTML 过滤 */
	protected static $_D = [];

	/** 经过 \Tango\Core\Filter 过滤的输入参数（原 $_GET/$_POST） */
	public static $IN = [];

	protected static $_bFail = FALSE;

	protected static $_sBaseDir = '';

	protected static $_sWww = '';

	protected static $_sScript;
	protected static $_sTpl;

	protected static $_bDelay; // 是否执行 Delay::run()

	protected static $_oLayout;
	protected static $_sLayout;

	protected static $_oThrow;
	protected static $_oThrowTpl;

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

	protected static $_bDebugTpl = FALSE;

	public const CONTENT_TYPE_LIST = [
		'html' => 'text/html',
		'txt' => 'text/plain',
		'json' => 'application/json',
		'jsonp' => 'application/javascript',
		'js' => 'application/javascript',
		'xml' => 'application/xml',
		'css' => 'text/css',
	];

	public $_bStop = FALSE;

	public function setStop() {
		$this->_bStop = TRUE;
	}

	public function __destruct() {
		if (!$this->_bStop && !static::isStop()) {
			static::run();
		}
	}

	public static function cacheForever(): void {

		if (
			!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])
			|| !empty($_SERVER['HTTP_IF_NONE_MATCH'])
		) {
			http_response_code(304);
			self::stop();
		}

		header('ETag: "cache-forever"');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', time()));

		self::setExpireMax();
	}

	public static function etag(string $sETag): void {

		$sETag = '"' . $sETag . '"';
		$sETagCheck = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
		if (
			$sETagCheck === $sETag
			|| $sETagCheck === 'W/' . $sETag
		) {
			http_response_code(304);
			self::stop();
		}

		header('ETag: ' . $sETag);
	}

	public static function setExpireMax() {
		header('Expires: Thu, 31 Dec 2037 23:55:55 GMT');
		header('Cache-Control: max-age=315360000');
	}

	public static function getBaseDir(): string {
		return self::$_sBaseDir;
	}

	protected static function _checkFileSafe(string $sPath, string $sSubDir) {
		$sBaseDir = self::$_sBaseDir . '/' . rtrim($sSubDir, '/') . '/';
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
			throw new Exception('unknown content type "' . $sType . '" / ' . implode(',', array_keys(self::CONTENT_TYPE_LIST)));
		}
		static::$_sContentType = $sType;
	}

	public static function getContentType(): string {
		return static::$_sContentType;
	}

	protected static function _sendContentTypeHeader(string $sContentType = '') {
		$sOut = self::CONTENT_TYPE_LIST[$sContentType ?: self::$_sContentType];
		header('Content-Type: ' . $sOut);
	}

	public static function setLayout(string $sLayout) {
		self::$_sLayout = $sLayout;
	}

	public static function getConfig(): array {
		return Config::get('page') ?: [];
	}

	public static function start(string $sScript, string $sBaseDir = ''): void {

		ob_start();

		self::$_sBaseDir = $sBaseDir ?: dirname(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file']);
		self::$_sScript = $sScript;

		// detect content-type by filename
		$sFilename = basename($sScript, '.php');
		if (strpos($sFilename, '.') !== FALSE) {
			foreach (self::CONTENT_TYPE_LIST as $sType => $_) {
				if (preg_match('#\.' . $sType . '$#', $sFilename)) {
					static::$_sContentType = $sType;
					break;
				}
			}
		}

		register_shutdown_function([get_called_class(), 'run']);
		self::run(FALSE);
	}

	protected static function _loop() {
		while (!self::isStop()) {
			switch (self::$_iStep) {
			case self::STEP_INIT:
				self::_www();
				break;
			case self::STEP_WWW:
				self::_content();
				break;
			case self::STEP_CONTENT:
				self::_tpl();
				break;
			case self::STEP_TPL:
				self::_layout();
				break;
			default:
				self::$_iStep = self::STEP_END;
				break;
			}
		}
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

		if (strpos(self::$_sScript, '..') !== FALSE
			|| strpos(self::$_sScript, '/_') !== FALSE
		) {
			self::$_oThrow = new \Exception('Looks like an attack: ' . self::$_sScript);
			return;
		}

		$sFile = self::$_sBaseDir . '/www' . self::$_sScript;

		if (!self::_checkFileSafe($sFile, 'www')) {
			return;
		}

		if (self::_hookPreWww()) {
			return;
		}

		$T =& self::$_T;
		$D =& self::$_D;
		$_IN =& self::$IN;

		$sFileDir = dirname($sFile);
		$sFileBefore = $sFileDir . '/_before.inc.php';
		$sFileAfter  = $sFileDir . '/_after.inc.php';

		self::$_fTimeWww = microtime(TRUE);
		try {

			if (file_exists($sFileBefore)) {
				$_sFile = $sFileBefore;
				(function () use ($_sFile, &$T, &$D, &$_IN) {
					require $_sFile;
				})();
			}

			$_sFile = $sFile;
			(function () use ($_sFile, &$T, &$D, &$_IN) {
				require $_sFile;
			})();

			if (file_exists($sFileAfter)) {
				$_sFile = $sFileAfter;
				(function () use ($_sFile, &$T, &$D, &$_IN) {
					require $_sFile;
				})();
			}

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
				self::_sendContentTypeHeader();
			}
			return;
		}

		if (static::$_sContentType === 'html') {
			self::$_T = HTML::escape(self::$_T);
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
				if (!$sCallback) {
					$sCallback = 'callback';
				}
			}

			self::$_iStep = self::STEP_END;
			self::_sendContentTypeHeader();

			if (strlen($sCallback)) {
				echo $sCallback . '(';
			}
			echo json_encode(self::$_T, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			if (strlen($sCallback)) {
				echo ')';
			}
			return;
		}
	}

	protected static function _tpl(): void {

		self::$_iStep = self::STEP_TPL;

		$sFile = self::$_sTpl ?: HTML::getTpl(self::$_sScript);
		self::_checkFileSafe($sFile, 'tpl');

		if (self::$_oThrow) {
			self::$_bDebugTpl = TRUE;
			$sFile = static::_fallbackTplDebug();
		}

		$T =& self::$_T;
		$D =& self::$_D;
		$_IN =& self::$IN;

		if (self::$_oThrow) {
			self::$_oThrowTpl = self::$_oThrow;
			self::$_oThrow = NULL;
		}

		$sFileDir = dirname($sFile);
		$sFileBefore = $sFileDir . '/_before.inc.php';
		$sFileAfter  = $sFileDir . '/_after.inc.php';

		self::$_fTimeTpl = microtime(TRUE);

		$e = NULL;
		if (is_readable($sFile)) {
			try {
				if (file_exists($sFileBefore)) {
					$_sFile = $sFileBefore;
					(function () use ($_sFile, &$T, &$D, &$_IN) {
						require $_sFile;
					})();
				}

				$_sFile = $sFile;
				(function () use ($_sFile, $T, $D, $_IN) {
					require $_sFile;
				})();

				if (file_exists($sFileAfter)) {
					$_sFile = $sFileAfter;
					(function () use ($_sFile, &$T, &$D, &$_IN) {
						require $_sFile;
					})();
				}
			} catch(\Throwable $e) {
			}
		} else {
			$e = new \Exception('no tpl ' . $sFile);
		}

		if ($e) {
			ob_clean();

			if (self::$_bDebugTpl) {
				self::stop(FALSE);
				return;
			}

			self::$_oThrow = $e;
			self::$_iStep = self::STEP_CONTENT;
		}
	}

	protected static function _layout(): void {

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

	public static function addHookPreWww(callable $cb): void {
		static::$_lHookPreWww[] = $cb;
	}

	protected static function _hookPreWww(): bool {
		foreach (static::$_lHookPreWww as $cb) {
			if ($cb(self::$_sScript)) {
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

	/**
	 * 仅用于 debug tpl
	 */
	public static function getThrow(): ?\Throwable {
		return self::$_oThrowTpl;
	}

	public static function run(bool $bKeep = TRUE) {

		if (self::isStop()) {
			return;
		}

		$o = $bKeep ? new static() : FALSE;
		static::_loop();
		if ($o) {
			$o->setStop();
		}
	}

	public static function initLayout() {

		// 延迟加载，如果没有 HTML 输出，可以避免加载 Layout 类的开销

		if (!self::$_oLayout) {
			self::$_oLayout = new \Tango\Page\Layout();
		}
		return self::$_oLayout;
	}

	public static function jump($sURL) {
		ob_clean();
		if (preg_match('#^/#', $sURL)) {
			$sBase = static::getConfig()['site_url']
				?? ('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/');
			$sURL = $sBase . ltrim($sURL, '/');
		}
		header('Location: ' . $sURL);
		self::stop();
	}

	public static function isStop() {
		return self::$_iStep === self::STEP_END;
	}

	public static function stop(bool $bExit = TRUE) {
		self::$_iStep = self::STEP_END;
		if ($bExit) {
			exit;
		}
	}
}
