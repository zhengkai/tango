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

	private static $_bFail = FALSE;

	private static $_bInit = FALSE;
	private static $_sStep = 'init';

	private static $_sBaseDir;

	private static $_bWww = TRUE;
	private static $_bWwwOverflow = FALSE;

	private static $_sURI;
	private static $_sTpl;

	private static $_bDelay; // 是否执行 Delay::run()

	private static $_oLayout;
	private static $_sLayout;

	private static $_oThrow;

	private static $_fTimeWww;
	private static $_fTimeTpl;

	private static $_sContentType = 'html';
	private static $_bSendContentTypeHeader = FALSE;

	const CONTENT_TYPE_LIST = [
		'html' => 'text/html',
		'txt' => 'text/plain',
		'json' => 'application/json',
		'jsonp' => 'application/javascript',
		'xml' => 'application/xml',
	];

	const ERROR_STOP_CODE_LIST = [
		E_ERROR,
		E_CORE_ERROR,
		E_COMPILE_ERROR,
		E_USER_ERROR,
		E_RECOVERABLE_ERROR,
	];

	public static function wwwOverflow() {
		self::$_bWwwOverflow = TRUE;
		return FALSE;
	}

	public static function getBaseDir() {
		return self::$_sBaseDir;
	}

	protected static function _checkPathSafe(string $sPath, string $sSubDir = '') {
		$sBaseDir = self::$_sBaseDir . '/';
		if ($sSubDir) {
			$sBaseDir .= trim($sSubDir, '/') . '/';
		}
		return strpos($sPath, $sBaseDir) === 0;
	}

	public static function setContentType(string $sType) {

		if (!in_array(self::$_sStep, ['init', 'www'])) {
			self::$_sStep = 'end';
			throw new TangoException('only can be use in www page');
		}
		if (!array_key_exists($sType, self::CONTENT_TYPE_LIST)) {
			self::$_sStep = 'end';
			throw new TangoException('unknown content type "'. $sType .'" / '.implode(array_keys(self::CONTENT_TYPE_LIST)));
		}
		self::$_sContentType = $sType;
	}

	protected static function sendContentTypeHeader(string $sContentType = '') {

		if (self::$_bSendContentTypeHeader) {
			self::$_sStep = 'end';
			throw new TangoException('duplicate set');
		}
		self::$_bSendContentTypeHeader = TRUE;

		if (array_key_exists($sContentType, self::CONTENT_TYPE_LIST)) {

			$sOut = self::CONTENT_TYPE_LIST[$sContentType];

		} else if ($sContentType) {

			$sOut = $sContentType;

		} else {

			$sOut = self::CONTENT_TYPE_LIST[self::$_sContentType];
		}

		header('Content-Type: ' . $sOut);
	}

	public static function setLayout(string $sLayout) {
		self::$_sLayout = $sLayout;
	}

	public static function getConfig() {
		return Config::get('page');
	}

	public static function exceptionHandler(\Throwable $ex) {
		self::$_oThrow = $ex;
		if (Config::isDebug()) {
			error_log('PHP Fatal error: ' . $ex->getMessage() . ' in file ' . $ex->getFile() . ':' . $ex->getLine());
		}
	}

	public static function start($sURI) {

		if (!self::$_bInit) {
			set_exception_handler([get_called_class(), 'exceptionHandler']);
			register_shutdown_function([get_called_class(), 'shutdown']);
		}
		self::$_bInit = TRUE;

		if (ob_get_length() !== FALSE) {
			ob_end_flush();
		}

		if (!self::$_sBaseDir) {
			self::$_sBaseDir = dirname(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file']);
		}

		self::$_sURI = $sURI;

		self::_www();

		if (http_response_code() === 404) {
			static::_notfoundPage();
			return;
		} else if (!self::$_bWww) {
			return;
		}

		switch (self::$_sContentType) {

			case 'html':
				if (self::$_bWwwOverflow || ob_get_length()) {
					ob_end_flush();
					self::$_sStep = 'end';
					break;
				}
				ob_end_clean();

				self::$T = HTML::escape(self::$T);

				self::_tpl();

				self::$_sStep = 'end';
				break;

			case 'txt':
				self::$_sStep = 'end';
				self::sendContentTypeHeader();
				ob_end_flush();
				break;

			case 'json':
				self::$_sStep = 'end';
				self::sendContentTypeHeader();
				if ($iLength = ob_get_length()) {
					trigger_error('www output when json, length = ' . $iLength);
					ob_end_flush();
				} else {
					ob_end_clean();
				}
				echo json_encode(self::$T, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
				break;

			default:
				self::$_sStep = 'end';
				ob_end_clean();
				self::sendContentTypeHeader('txt');
				echo 'ERROR: incomplete content-type parser "', self::$_sContentType, '"', "\n";
				break;
		}

		if (self::$_bDelay) {
			fastcgi_finish_request();
			Delay::run();
		}
	}

	public static function doDelay() {
		if (PHP_SAPI === 'fpm-fcgi') {
			self::$_bDelay = TRUE;
		}
	}

	private static function _www() {

		$sFile = self::$_sBaseDir . '/www' . self::$_sURI;
		if (!is_file($sFile)) {
			self::_missingPage(self::$_sURI, $sFile);
			return;
		}
		if (!self::_checkPathSafe($sFile, 'www')) {
			self::$_sStep = 'end';
			throw new TangoException('path ' . self::$_sURI . ' unsafe');
		}

		$T =& self::$T;
		$D =& self::$D;
		$_IN =& self::$IN;

		self::$_sStep = 'www';

		if (self::$_sContentType === 'html') {
			ob_start([__CLASS__, 'wwwOverflow'], 4096);
		} else {
			ob_start();
		}

		self::$_fTimeWww = microtime(TRUE);
		require $sFile;
		self::$_fTimeWww = microtime(TRUE) - self::$_fTimeWww;
	}

	public static function getTimeWww() {
		return self::$_fTimeWww;
	}

	public static function getTimeTpl() {
		return self::$_fTimeTpl;
	}

	public static function setTpl(string $sTpl) {

		if (!preg_match('#\.php$#', $sTpl)) {
			$sTpl .= '.php';
		}

		if (substr($sTpl, 0, 1) === '/') {
			self::$_sTpl = $sTpl;
		} else {
			self::$_sTpl = dirname(self::$_sURI) . '/' . $sTpl;
		}
	}

	protected static function _tpl(string $sURI = '') {

		ob_start();

		$T =& self::$T;
		$D =& self::$D;
		$_IN =& self::$IN;

		self::$_sStep = 'tpl';

		if (!$sURI) {
			$sURI = self::$_sTpl ?: self::$_sURI;
		}
		$sFile = self::$_sBaseDir . '/tpl' . $sURI;

		if (!self::_checkPathSafe($sFile, 'tpl')) {
			self::$_sStep = 'end';
			throw new TangoException('path ' . $sURI . ' unsafe');
		}

		self::$_fTimeTpl = microtime(TRUE);
		require $sFile;
		self::$_fTimeTpl = microtime(TRUE) - self::$_fTimeTpl;

		$sBody = ob_get_clean();

		self::$_sStep = 'layout';
		$oLayout = static::initLayout();
		if (self::$_sLayout) {
			$oLayout->setLayout(self::$_sLayout);
		}
		$oLayout->setBody($sBody);
		$oLayout->run();

		return TRUE;
	}

	protected static function _missingPage($sURI, $sFile) {
		self::$_bWww = FALSE;
		self::$_sStep = 'end';
		self::setContentType('txt');
		echo 'ERROR: missing uri ' . $sURI;
	}

	protected static function _notfoundPage() {

		if (self::$_bFail) {
			return;
		}
		self::$_bFail = TRUE;

		require dirname(__DIR__) . '/Page/tpl/404_notfound.php';
	}

	protected static function _debugPage() {

		if (self::$_bFail) {
			return;
		}
		self::$_bFail = TRUE;

		if (Config::isDebug()) {
			require dirname(__DIR__) . '/Page/tpl/500_debug.php';
		} else {
			http_response_code(500);
		}
	}

	public static function getThrow() {
		return self::$_oThrow;
	}

	public static function shutdown() {

		if (self::$_sStep === 'end') {
			// 正常结束
			return;
		}

		$aError = error_get_last();
		if ($aError && self::isStopError($aError['type'])) {
			// 还不知道什么情况下会 php7 报 error 而不是 ErrorException，
			// 不知道如何触发，先这么写上吧
			self::$_oThrow = new \ErrorException($aError['message'], 0, $aError['type'], $aError['file'], $aError['line']);
			error_clear_last();
			static::_debugPage();
			return;
		}

		switch (self::$_sStep) {

			case 'www':

				if (self::$_fTimeWww) {
					self::$_fTimeWww = microtime(TRUE) - self::$_fTimeWww;
				}
				ob_end_clean();

			case 'tpl':

				if (!self::$_oThrow) {
					self::$_oThrow = new TangoException(self::$_sStep . ' 异常，错误：使用 return，别用 exit');
				}
				static::_debugPage();
				break;

			case 'layout':
				echo 'error when layout';
				break;

			default:
				// init 或其他未知阶段
				break;
		}
		// echo 'step = ', self::$_sStep, "\n";
		// echo 'shutdown', "\n";
	}

	public static function isStopError($iError) {
		return in_array($iError, self::ERROR_STOP_CODE_LIST);
	}

	public static function initLayout() {

		// 这么干是为了延迟加载，如果没有 HTML 输出，可以避免加载 Layout 类的开销

		if (!self::$_oLayout) {
			self::$_oLayout = new \Tango\Page\Layout();
		}
		return self::$_oLayout;
	}

	public static function jump($sURL) {
		if (self::$_sStep === 'www') {
			ob_end_clean();
		} else if (self::$_sStep !== 'init') {
			throw new \Exception('only can be use in www page, now step ' . self::$_sStep);
		}
		self::$_sStep = 'end';
		self::$_bWww = FALSE;
		if (preg_match('#^/#', $sURL)) {
			$sURL = Config::get('page')['site_url'] . ltrim($sURL, '/');
		}
		header('Location: ' . $sURL);
		return;
	}

	public static function stopWww() {
		if (self::$_sStep === 'www') {
			self::$_sStep = 'end';
			self::$_bWww = FALSE;
		} else if (self::$_sStep !== 'init') {
			throw new \Exception('only can be use in www page, now step ' . self::$_sStep);
		}
	}

	public static function getStep() {
		return self::$_sStep;
	}
}
