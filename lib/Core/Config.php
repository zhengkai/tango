<?php
namespace Tango\Core;

/**
 * 注册配置文件的路径，在需要的时候再加载
 */
class Config {

	static protected $_lStore = [];
	static protected $_lFile = [];
	static protected $_lFileDefault = [];

	static public function setFile($sName, $sPath) {
		self::_setFile($sName, $sPath);
	}

	static public function setFileDefault($sName, $sPath) {
		self::_setFile($sName, $sPath, TRUE);
	}

	static protected function _setFile($sName, $sPath, $bDefault = FALSE) {

		$sVar = $bDefault ? '_lFileDefault' : '_lFile';
		$sCurrentPath =& self::${$sVar}[$sName];
		if ($sCurrentPath) {
			throw new TangoException('"'.$sName.'"'.($bDefault ? '(default)' : '').' define duplicate');
			exit;
		}
		$sCurrentPath = $sPath;
		return TRUE;
	}

	static public function get($sName) {
		$aReturn =& self::$_lStore[$sName];

		if (!$aReturn) {

			$sFileDefault =& self::$_lFileDefault[$sName];
			// if (!Tango::isInit()) {
			// 	return $sFileDefault ? require $sFileDefault : [];
			// }

			$sFile =& self::$_lFile[$sName];
			$aReturn = array_replace_recursive(
				$sFileDefault ? require $sFileDefault : [],
				$sFile ? require $sFile : []
			);
		}
		return $aReturn;
	}

	static public function getFile($sName) {
		$sFile        =& self::$_lFile[$sName];
		$sFileDefault =& self::$_lFileDefault[$sName];
		return [
			'file' => $sFile,
			'file_default' => $sFileDefault,
		];
	}
}
