<?php
namespace Tango\Core;

use Tango\Page\HTML;

class Page {

	protected static $_aExt = FALSE;
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

	protected static $_bParse = FALSE;

	protected static $_bWellDone = FALSE;

	public static function isWellDone() {
		return self::$_bWellDone;
	}

	public static function reset() {
		self::$_bParse = FALSE;
	}

	public static function error($sError) {
		if (self::$_bParse) {
			throw new TangoException('Page has been sent');
		}
		Tango::$T['error'] = $sError;
		return;
	}

	public static function debugGate() {
		if (!Tango::isDebug()) {
			self::error('http404');
			return FALSE;
		}
		return TRUE;
	}

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

	public static function get() {
		return self::$_aExt;
	}

	public static function stopParse() {
		self::$_bParse = TRUE;
	}

	public static function jump($sURL) {
		if (self::$_bParse) {
			throw new TangoException('jump before parse');
		}
		self::$_bParse = TRUE;
		header('Location: '.$sURL);
		exit;
	}

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

	protected static function _parseText() {
		Tango::$T += ['output' => ''];
		echo Tango::$T['output'];
		return TRUE;
	}
	protected static function _parseJson() {
		echo json_encode(Tango::$T, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		return TRUE;
	}
}
