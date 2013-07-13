<?php
namespace Tango\Core;

class HTML {

	static protected $_lTpl = [
		'head' => '/head',
		'foot' => '/foot',
		'nav'  => '/nav',
		'error' => '/error',
	];

	static protected $_lJS = [];
	static protected $_lCSS = [
		'http://yui.yahooapis.com/pure/0.2.0/pure-min.css',
	];

	static public function run($T) {
		require self::_getPath('head');
		require self::_getPath('nav');
		echo '<pre>';
		echo htmlspecialchars(print_r($T, TRUE));
		echo '</pre>';
		require self::_getPath('foot');
	}

	static public function setTpl($lTpl, $sValue = NULL) {
		if (is_string($aTpl)) {
			$lTpl = [$aTpl => $sValue];
		}
		foreach ($lTpl as $sKey => $sValue) {
			if (!$sValue) {
				die('setTpl "'.$sKey.'" empty');
			}
			$sCurrent =& self::$_lTpl[$sKey];
			if (!$sOrig) {
				die('setTpl "'.$sKey.'" unknown');
			}
			$sCurrent = $sValue;
		}
	}

	static protected function _getPath($sTpl) {
		return SITE_ROOT.'/tpl'.self::$_lTpl[$sTpl].'.php';
	}

	static public function getTitle() {
		return 'Tango';
	}

	static public function getMeta() {
		$sReturn = '';
		foreach (self::$_lCSS as $sCSS) {
			$sReturn .= '<link rel="stylesheet" href="'.$sCSS.'" type="text/css" />'."\n";
		}
		return $sReturn;
	}

	static public function addJS($sFile) {
		self::$_lJS[] = $sFile;
	}

	static public function addCSS($sFile) {
		self::$_lCSS[] = $sFile;
	}
}
