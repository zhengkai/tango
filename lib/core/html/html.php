<?php
namespace Tango\Core;

Config::setFileDefault('html', dirname(dirname(__DIR__)).'/config/html.php');

class HTML {

	static protected $_lTpl = [
		'head'  => '/head',
		'foot'  => '/foot',
		'nav'   => '/nav',
		'error' => '/error',
		'main'  => '',
	];

	static protected $_lJS = [];
	static protected $_lCSS = [];
	static protected $_sAddMeta = '';

	static protected $_sTitle = '';

	static protected $_bRobotsIndex = TRUE;
	static protected $_bRobotsFollow = TRUE;

	static public function run() {

		if (!self::$_lTpl['main']) {
			self::$_lTpl['main'] = substr($_SERVER['SCRIPT_NAME'], 0, -4);
		}

		$T =& Tango::$T;
		$D =& Tango::$D;

		$T = self::escape($T);

		$s = '';

		$bError = FALSE;
		ob_start();
		try {
			include self::getTpl('main');
			$s = trim(ob_get_clean());
		} catch(\Exception $e) {
			ob_clean();
			$bError = TRUE;
			TangoException::handler($e);
		}

		if (!$bError) {
			if ($aError = Tango::getStopError()) {
				$bError = TRUE;
			}
		}

		if ($bError) {

			Tango::$T['error'] = 'http500';
			HTML::setTpl('main', '/error/500');

			ob_start();
			include self::getTpl('main');
			$s = trim(ob_get_clean());
		}

		Layout::run($s);
	}

	static public function setFollow($bFollow) {
		self::$_bRobotsFollow = (bool)$bFollow;
	}

	static public function setIndex($bIndex) {
		self::$_bRobotsIndex = (bool)$bIndex;
	}

	static public function setTpl($lTpl, $sValue = NULL) {

		if (is_string($lTpl)) {
			$lTpl = [$lTpl => $sValue];
		}
		foreach ($lTpl as $sKey => $sValue) {
			if (!$sValue) {
				die('setTpl "'.$sKey.'" empty');
			}
			if (!isset(self::$_lTpl[$sKey])) {
				die('setTpl "'.$sKey.'" unknown');
			}
			self::$_lTpl[$sKey] = $sValue;
		}
	}

	static public function getTpl($sTpl) {
		return self::_getFile(self::$_lTpl[$sTpl].'.php');
	}

	static protected function _getFile($sFile) {
		return SITE_ROOT.'/tpl'.$sFile;
	}

	static public function setTitle($sTitle) {
		self::$_sTitle = $sTitle;
	}

	static public function getTitle() {
		return (self::$_sTitle ? self::$_sTitle.' - ' : '')
			.Config::get('html')['title'];
	}

	static public function getMeta() {
		$sReturn = '';

		// nofollow, noindex
		if (!self::$_bRobotsIndex || !self::$_bRobotsFollow) {
			$sReturn .= '<meta name="ROBOTS" content="'
				.(self::$_bRobotsIndex  ? 'INDEX'  : 'NOINDEX').', '
				.(self::$_bRobotsFollow ? 'FOLLOW' : 'NOFOLLOW').'">'."\n";
		}

		// css
		foreach (array_merge(Config::get('html')['css'], self::$_lCSS) as $sCSS) {
			$sReturn .= '<link rel="stylesheet" href="'.$sCSS.'" type="text/css" />'."\n";
		}

		// js
		foreach (array_merge(Config::get('html')['js'], self::$_lJS) as $sJS) {
			$sReturn .= '<script src="'.$sJS.'"></script>'."\n";
		}

		if (self::$_sAddMeta) {
			$sReturn .= self::$_sAddMeta."\n";
		}

		return $sReturn;
	}

	static public function addMeta($s) {
		self::$_sAddMeta = trim($s);
	}

	static public function addJS($sFile) {
		self::$_lJS[] = $sFile;
	}

	static public function addCSS($sFile) {
		self::$_lCSS[] = $sFile;
	}

	static public function colorGradient($fRate, $sColorA, $sColorB = '#FFFFFF') {

		$lColorA = self::_colorRGB($sColorA);
		$lColorB = self::_colorRGB($sColorB);

		$sReturn = '';
		foreach (range(0, 2) as $i) {
			$iColor = $lColorB[$i] + ($lColorA[$i] - $lColorB[$i]) / 2 * $fRate;
			$sReturn .= sprintf('%02s', dechex(round($iColor)));
		}

		return '#'.$sReturn;
	}

	static protected function _colorRGB($sColor) {
		$sError = 'unknown color "'.$sColor.'"';
		$sColor = strtolower($sColor);

		$sColorOrig = $sColor;

		$sColor = preg_replace('/^#/', '', $sColor);
		if (strlen($sColor) > 6 || !preg_match('#^[0-9a-f]+$#', $sColor)) {
			throw new TangoException($sError);
		}

		switch (strlen($sColor)) {
			case 3:
				$lColor = str_split($sColor, 1);
				$sColor = $lColor[0].$lColor[0].$lColor[1].$lColor[1].$lColor[2].$lColor[2];
				break;
			case 6:
				break;
			default:
				throw new TangoException($sError);
				break;
		}

		$lColor = str_split($sColor, 2);
		$lColor = array_map('hexdec', $lColor);

		return $lColor;
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

	static public function test($s = 'abc') {
		self::_test();
	}

	static public function _test() {
		throw new TangoException('something error', 2);
	}
}
