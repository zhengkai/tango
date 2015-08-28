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

Config::setFileDefault('tango', dirname(__DIR__) . '/Config/tango.php');

/**
 * 配置信息
 *
 * 注册配置文件的路径，在需要的时候再加载
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class Config {

	/** 已读取的配置信息 */
	protected static $_lStore = [];

	/** 缺省目录 */
	protected static $_sDir = NULL;

	/** 命名对应的文件列表 */
	protected static $_lFile = [];

	/** 命名对应的缺省文件列表 */
	protected static $_lFileDefault = [];

	/** debug mode */
	protected static $_bDebug;

	public static function isDebug() {
		if (static::$_bDebug === NULL) {
			static::$_bDebug = (boolean)static::get('tango')['debug']['enable'];
		}
		return static::$_bDebug;
	}

	public static function setDebug(bool $bDebug) {
		return static::$_bDebug = $bDebug;
	}

	/**
	 * 缺省目录
	 *
	 * 如果 setFile 没有指定，会去 setDir 目录看有没有特定文件，有则加载
	 *
	 * @param string $sPath
	 * @static
	 * @access public
	 * @return void
	 */
	public static function setDir($sPath) {
		if (static::$_sDir) {
			throw new TangoException('dir define duplicate');
		}
		$sPath = rtrim(trim($sPath), '/');
		if (!is_dir($sPath)) {
			return FALSE;
		}
		static::$_sDir = $sPath . '/';
		return TRUE;
	}

	/**
	 * 设定“配置文件”的路径，只有在需要的时候才去读取配置
	 *
	 * @param string $sName 配置的命名空间
	 * @param string $sPath 配置文件的路径
	 * @static
	 * @access public
	 * @return void
	 */
	public static function setFile($sName, $sPath) {
		static::_setFile($sName, $sPath);
	}

	/**
	 * 设定“配置文件”的默认路径
	 *
	 * 这个方法通常是在定义类的文件里出现，确保所有初始值已经有了，而避免找不到 key 出现 php notice
	 * 可以参考 Drive\DB.php 文件开头的用法
	 *
	 * @see \Tango\Drive\DB class
	 *
	 * @param string $sName 配置的命名空间
	 * @param string $sPath 配置文件的路径
	 * @static
	 * @access public
	 * @return void
	 */
	public static function setFileDefault($sName, $sPath) {
		static::_setFile($sName, $sPath, TRUE);
	}

	/**
	 * setFile 和 setFileDefault 的公共部分
	 *
	 * @param string $sName 配置的命名空间
	 * @param string $sPath 配置文件的路径
	 * @param boolean $bDefault 是否是缺省配置
	 * @static
	 * @access protected
	 * @throws TangoException
	 * @return bool
	 */
	protected static function _setFile($sName, $sPath, $bDefault = FALSE) {

		$sVar = $bDefault ? '_lFileDefault' : '_lFile';
		$sCurrentPath =& static::${$sVar}[$sName];
		if ($sCurrentPath) {
			if (!$bDefault || $sPath !== $sCurrentPath) {
				throw new TangoException('"'.$sName.'"'.($bDefault ? '(default)' : '').' define duplicate');
			}
		}
		$sCurrentPath = $sPath;
		return TRUE;
	}

	/**
	 * 获取配置
	 *
	 * @param string $sName 配置的命名空间
	 * @static
	 * @access public
	 * @return array
	 */
	public static function get($sName) {
		$aReturn =& static::$_lStore[$sName];

		if (!$aReturn) {

			$sFileDefault =& static::$_lFileDefault[$sName];
			$sFile =& static::$_lFile[$sName];

			if (!$sFile && static::$_sDir) {
				$sGuess = static::$_sDir . $sName . '.php';
				if (file_exists($sGuess)) {
					$sFile = $sGuess;
				}
			}

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
	 * @param string $sName 配置的命名空间
	 * @static
	 * @access public
	 * @return array
	 */
	public static function getFile($sName) {
		$sFile        =& static::$_lFile[$sName];
		$sFileDefault =& static::$_lFileDefault[$sName];
		return [
			'file' => $sFile,
			'file_default' => $sFileDefault,
		];
	}
}
