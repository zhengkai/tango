<?php
namespace Tango\Core;

class HTML {

	static protected $_lTpl = [
		'head'  => '/head',
		'foot'  => '/foot',
		'nav'   => '/nav',
		'error' => '/error',
	];

	static protected $_lJS = [
		'http://code.jquery.com/jquery-2.0.3.min.js',
	];
	static protected $_lCSS = [
		'http://yui.yahooapis.com/pure/0.2.0/pure-min.css',
	];

	static public function run($T, $D) {

		ob_start();
		require self::_getFile($_SERVER['SCRIPT_NAME']);
		$s = ob_get_clean();

		require self::_getTpl('head');
		echo "\n";
		require self::_getTpl('nav');
		echo "\n";

		echo trim($s);

		echo "\n\n";

		require self::_getTpl('foot');
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

	static protected function _getTpl($sTpl) {
		return self::_getFile(self::$_lTpl[$sTpl].'.php');
	}

	static protected function _getFile($sFile) {
		return SITE_ROOT.'/tpl'.$sFile;
	}

	static public function getTitle() {
		return 'Tango';
	}

	static public function getMeta() {
		$sReturn = '';
		foreach (self::$_lCSS as $sCSS) {
			$sReturn .= '<link rel="stylesheet" href="'.$sCSS.'" type="text/css" />'."\n";
		}

		foreach (self::$_lJS as $sJS) {
			$sReturn .= '<script src="'.$sJS.'"></script>'."\n";
		}
		return $sReturn;
	}

	static public function addJS($sFile) {
		self::$_lJS[] = $sFile;
	}

	static public function addCSS($sFile) {
		self::$_lCSS[] = $sFile;
	}

	/**
	 * 递归对数组进行 HTML 转义（包括 key 和 value）
	 */
	static public function escape($mInput) {

		if (is_string($mInput)) {
			//$mRow = preg_replace("/\\p{C}|\\p{M}/u", "", $mRow);
			// TODO 防止各种火星文，有待测试，以后放到 class Filter 里

			return htmlspecialchars($mInput, ENT_QUOTES | ENT_HTML5);
		}

		if (!is_array($mInput)) {
			return $mInput;
		}

		$fnSelf = [__CLASS__, __FUNCTION__];

		$lReturn = [];
		foreach ($mInput as $mKey => $mValue) {
			$mKey = $fnSelf($mKey);
			$mValue = $fnSelf($mValue);
			$lReturn[$mKey] = $mValue;
		}

		return $lReturn;
	}
}
