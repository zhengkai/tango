<?php
namespace Tango\Core;

class Ext {

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

	static public function parse($T, $D) {
		$sExt = self::$_aExt['ext'];
		$call = [__CLASS__, '_parse'.ucfirst($sExt)];
		if (is_callable($call)) {
			header('Content-Type: '.self::$_aExt['mime'].'; charset=utf-8');
			$call($T, $D);
		} else {
			header('Content-Type: text/plain; charset=utf-8');
			echo "\n\t", 'Error: method '.$sExt.' incomplete', "\n";
		}
	}

	static protected function _parseHtml($T, $D) {
		HTML::run($T, $D);
	}

	static protected function _parseJson($T) {
		echo json_encode($T, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		return TRUE;
	}
}
