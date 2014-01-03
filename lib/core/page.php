<?php
namespace Tango\Core;

class Page {

	static protected $_aExt = FALSE;
	static protected $_lExt = [
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

	static protected $_bParse = FALSE;

	static protected $_bWellDone = FALSE;

	static public function isWellDone() {
		return self::$_bWellDone;
	}

	static public function error($sError) {
		if (self::$_bParse) {
			throw new TangoException('Page has been sent');
		}
		Tango::$T['error'] = $sError;
		exit;
	}

	static public function debugGate() {
		if (!Tango::isDebug()) {
			self::error('http404');
		}
	}

	static public function set($sExt, $bTry = FALSE) {
		if ($bTry && self::$_aExt) {
			trigger_error('ext exists');
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

	static public function get() {
		return self::$_aExt;
	}

	static public function noParse() {
		self::$_bParse = TRUE;
	}

	static public function jump($sURL) {
		if (self::$_bParse) {
			throw new TangoException('jump before parse');
		}
		self::$_bParse = TRUE;
		header('Location: '.$sURL);
		exit;
	}

	static public function parse() {

		if (self::$_bParse) {
			return FALSE;
		}
		self::$_bParse = TRUE;

		$sExt = self::$_aExt['ext'];

		if ($sExt === 'html') {

			if (($aError = error_get_last())
				&& !in_array($aError['type'], [E_NOTICE, E_USER_NOTICE])
			) {
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
			echo "\n\t", 'Error: method '.$sExt.' incomplete', "\n";
			return FALSE;
		}
	}

	static protected function _parseJson() {
		echo json_encode(Tango::$T, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		return TRUE;
	}
}
