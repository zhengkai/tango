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

	static public function error($sError) {
		if (self::$_bParse) {
			throw new TangoException('Page has been sent');
		}
		Tango::$T['error'] = $sError;
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

	static public function parse() {

		self::$_bParse = TRUE;

		$sExt = self::$_aExt['ext'];

		if ($sExt === 'html') {

			if (
				(!empty(Tango::$T['error']) && Tango::$T['error'] === 'http500')
				|| (
					($aError = error_get_last())
					&& !in_array($aError['type'], [E_NOTICE, E_USER_NOTICE])
				)
			) {
				//http_response_code(500);

				HTML::setTpl('main', '/error/500');

			} else if (!empty(Tango::$T['error']) && Tango::$T['error'] === 'http404') {

				HTML::setTpl('main', '/error/404');
			}

			HTML::run();
			return TRUE;
		}

		$call = [__CLASS__, '_parse'.ucfirst($sExt)];
		if (is_callable($call)) {
			header('Content-Type: '.self::$_aExt['mime'].'; charset=utf-8');
			$call($T, $D);
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