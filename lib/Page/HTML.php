<?php
/**
 * This file is part of the Tango Framework.
 *
 * (c) Zheng Kai <zhengkai@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tango\Page;

use \Tango\Core\Tango;
use \Tango\Core\Page;
use \Tango\Core\Config;

Config::setFileDefault('html', dirname(__DIR__).'/Config/html.php');

/**
 * HTML 页面输出相关
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class HTML {

	/** 默认模板路径 */
	protected static $_sTpl = '';
	protected static $_sTplType = '';

	/** JS 列表 */
	protected static $_lJS = [];

	/** CSS 列表 */
	protected static $_lCSS = [];

	/** 除 CSS/JS 外的额外 <meta> 段内的信息 */
	protected static $_sAddMeta = '';

	/** 页面 <title> 内容 */
	protected static $_sTitle = '';

	/** 如设成 false 则会在页面强调不要让搜索引擎索引本页 */
	protected static $_bRobotsIndex = TRUE;

	/** 如设成 false 则会在页面强调不要让搜索引擎索引本页所指向的链接 */
	protected static $_bRobotsFollow = TRUE;

	/**
	 * 如设成 false 则会在页面强调不要让搜索引擎索引本页所指向的链接
	 *
	 * @param boolean $bIndex
	 * @static
	 * @access public
	 * @return void
	 */
	public static function setFollow($bFollow) {
		self::$_bRobotsFollow = (bool)$bFollow;
	}

	/**
	 * 如设成 false 则会在页面强调不要让搜索引擎索引本页
	 *
	 * @param boolean $bIndex
	 * @static
	 * @access public
	 * @return void
	 */
	public static function setIndex($bIndex) {
		self::$_bRobotsIndex = (bool)$bIndex;
	}

	/**
	 * 设置 <title>
	 *
	 * @param string $sTitle
	 * @static
	 * @access public
	 * @return void
	 */
	public static function setTitle($sTitle) {
		self::$_sTitle = $sTitle;
	}

	public static function setTpl(string $sTpl) {

		self::$_sTpl = $sTpl;
	}

	public static function setTplType(string $sType) {

		self::$_sTplType = $sType;
	}

	public static function getTpl(string $sURI): string {

		$sBase = Page::getBaseDir() . '/tpl';

		if (!self::$_sTpl && !self::$_sTplType) {
			return $sBase . $sURI;
		}
		$sReturn = '';

		if (self::$_sTpl) {
			if (substr(self::$_sTpl, 0, 1) === '/') {
				$sReturn = self::$_sTpl;
			} else {
				$sReturn = dirname($sURI);
				if ($sReturn !== '/') {
					$sReturn .= '/';
				}
				$sReturn .= self::$_sTpl;
			}
		} else {
			$sReturn = substr($sURI, 0, -4);
		}

		if (self::$_sTplType) {
			$sReturn .= '.' . self::$_sTplType;
		}

		return $sBase . $sReturn . '.php';
	}

	/**
	 * 获取 <title>
	 *
	 * @static
	 * @access public
	 * @return string
	 */
	public static function getTitle() {
		return (self::$_sTitle ? self::$_sTitle.' - ' : '')
			. self::getConfig()['title'];
	}

	/**
	 * 获取整个 <meta> 段
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function getMeta() {
		$sReturn = '';

		// nofollow, noindex
		if (!self::$_bRobotsIndex || !self::$_bRobotsFollow) {
			$sReturn .= '<meta name="ROBOTS" content="'
				.(self::$_bRobotsIndex  ? 'INDEX'  : 'NOINDEX').', '
				.(self::$_bRobotsFollow ? 'FOLLOW' : 'NOFOLLOW').'">'."\n";
		}

		// css
		foreach (array_merge(self::getConfig()['css'], self::$_lCSS) as $sCSS) {
			$sReturn .= '<link rel="stylesheet" href="' . $sCSS . '" type="text/css" />'."\n";
		}

		// js
		foreach (array_merge(self::getConfig()['js'], self::$_lJS) as $sJS) {
			$sReturn .= '<script src="' . $sJS . '"></script>' . "\n";
		}

		if (self::$_sAddMeta) {
			$sReturn .= self::$_sAddMeta;
		}

		return $sReturn;
	}

	/**
	 * 添加除 CSS/JS 外的额外 <meta> 段内的信息
	 *
	 * @param string $s
	 * @static
	 * @access public
	 * @return void
	 */
	public static function addMeta($s) {
		self::$_sAddMeta .= trim($s) . "\n";
	}

	/**
	 * 添加 JS 路径
	 *
	 * @param string $sURL
	 * @static
	 * @access public
	 * @return void
	 */
	public static function addJS($sURL) {
		self::$_lJS[] = $sURL;
	}

	/**
	 * 添加 CSS 路径
	 *
	 * @param string $sURL
	 * @static
	 * @access public
	 * @return void
	 */
	public static function addCSS($sURL) {
		self::$_lCSS[] = $sURL;
	}

	/**
	 * 给定两个颜色和过渡程度，返回中间色，
	 * 可用在表格的不同数量显示不用程度颜色等场合
	 *
	 * @param float $fRate
	 * @param string $sColorA
	 * @param string $sColorB
	 * @static
	 * @access public
	 * @return void
	 */
	public static function colorGradient($fRate, $sColorA, $sColorB = '#FFFFFF') {

		$lColorA = self::_colorRGB($sColorA);
		$lColorB = self::_colorRGB($sColorB);

		$sReturn = '';
		foreach (range(0, 2) as $i) {
			$iColor = $lColorB[$i] + ($lColorA[$i] - $lColorB[$i]) / 2 * $fRate;
			$sReturn .= sprintf('%02s', dechex(round($iColor)));
		}

		return '#'.$sReturn;
	}

	/**
	 * 将 HTML 颜色（如 #0099FF）转换为 RGB 三个整数的数组
	 *
	 * @param string $sColor
	 * @static
	 * @access protected
	 * @return array
	 */
	protected static function _colorRGB($sColor) {
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
				$sColor = $lColor[0] . $lColor[0]
					. $lColor[1] . $lColor[1]
					. $lColor[2] . $lColor[2];
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
	 *
	 * @param string|array $mInput
	 * @static
	 * @access public
	 * @return string|array
	 */
	public static function escape($mInput) {

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

	/**
	 * 读取相关配置
	 *
	 * @static
	 * @access public
	 * @return array
	 */
	public static function getConfig() {
		return \Tango\Core\Config::get('html');
	}
}
