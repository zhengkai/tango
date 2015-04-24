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

/**
 * 配置信息
 *
 * 注册配置文件的路径，在需要的时候再加载
 */
class Config {

	/**
	 * 已读取的配置信息
	 */
	static protected $_lStore = [];

	static protected $_lFile = [];
	static protected $_lFileDefault = [];

	/**
	 * 设定“配置文件”的路径，只有在需要的时候才去读取配置
	 *
	 * @param mixed $sName 配置的命名空间
	 * @param mixed $sPath 配置文件的路径
	 * @static
	 * @access public
	 * @return void
	 */
	static public function setFile($sName, $sPath) {
		self::_setFile($sName, $sPath);
	}

	/**
	 * 设定“配置文件”的默认路径
	 * 这个方法通常是在定义类的文件里出现，确保所有初始值已经有了，而避免找不到 key 出现 php notice
	 * 可参考 ../Drive/DB.php
	 *
	 * @param mixed $sName 配置的命名空间
	 * @param mixed $sPath 配置文件的路径
	 * @static
	 * @access public
	 * @return void
	 */
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

	/**
	 * 获取配置
	 *
	 * @param mixed $sName 配置的命名空间
	 * @static
	 * @access public
	 * @return array
	 */
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

	/**
	 * 获取配置文件的路径
	 *
	 * @param mixed $sName 配置的命名空间
	 * @static
	 * @access public
	 * @return array
	 */
	static public function getFile($sName) {
		$sFile        =& self::$_lFile[$sName];
		$sFileDefault =& self::$_lFileDefault[$sName];
		return [
			'file' => $sFile,
			'file_default' => $sFileDefault,
		];
	}
}
