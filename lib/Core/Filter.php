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
 *  过滤输入参数，保障安全
 *
 *  这是一个使用范例：
 *
 *  Filter::run("POST", array(
 *  	"group_id" => "int",
 *  	"topic_id" => "int",
 *  	"post_id" => "int",
 *  	"title"   => "string",
 *  	"content" => "longString",
 *  ));
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */

class Filter {

	/** 用来控制只检查一次 */
	protected static $_bCheck = FALSE;

	/**
	 * 判断邮箱合法性的正则
	 *
	 * not the full regexp, it is too crazy
	 * http://www.ex-parrot.com/~pdw/Mail-RFC822-Address.html
	 */
	protected static $_pEmail = '#^[0-9a-z]([\+\.\-_0-9a-z][0-9a-z]+)*[0-9a-z]?@[0-9a-z]([\.\-_0-9a-z][0-9a-z]+)\.[a-z]{2,}$#i';

	/**
	 * 工作方法
	 *
     * @param string $sMethod "POST" 或 "GET"
     * @param array $lRule 包含要获取的参数的 key 和实际类型，并进行转换
	 *     字符串会被 utf8 编码过滤，不符合 utf8 编码的字符被丢弃
     *     需要注意默认的 int 不允许有负值，否则请使用 signedInt
	 */
	public static function run($sMethod = "GET", $lRule = []) {

		if (self::$_bCheck) {
			throw new TangoException('filter checked');
		}
		self::$_bCheck = TRUE;

		if ($sMethod == 'POST' || $sMethod == 'POST_NO_REF_CHECK') {
			if ($sMethod == 'POST') {
				// TODO: referer check
			}
			$aParm =& $_POST;
		} else if ($sMethod == 'GET') {
			$aParm =& $_GET;
		} else {
			throw new TangoException('unknown HTTP method "'.$sMethod.'"');
		}

		$_IN =& Tango::$IN;

		foreach ($lRule as $sKey => $sType) {
			$mValue =& $aParm[$sKey];
			if ($sType !== 'array' && is_array($mValue)) {
				$_IN[$sKey] = FALSE;
				continue;
			}

			switch ($sType) {
				case 'time':
					$sValue = iconv('UTF-8', 'UTF-8//IGNORE', trim($mValue));
					$mValue = strtotime($sValue) ?: 0;
					break;
				case 'str':
					$sValue = iconv('UTF-8', 'UTF-8//IGNORE', trim($mValue));
					if (strlen($mValue) > 1024) {
						$mValue = '';
					}
					break;
				case 'longStr':
					$mValue = iconv('UTF-8', 'UTF-8//IGNORE', trim($mValue));
					break;
				case 'bin':
				case 'array':
					break;
				case 'int':
					$mValue = (int)$mValue;
					if ($mValue < 1) {
						$mValue = 0;
					}
					break;
				case 'signedInt':
					$mValue = (int)$mValue;
					break;
				case 'bool':
					break;
				case "hex":
					if (!preg_match('#^[0-9a-f]{0,1024}$#', $mValue) || ((strlen($mValue) % 2) !== 0)) {
						$mValue = FALSE;
					}
					break;
				case 'json';
					if (!is_string($mValue) || strlen($mValue) > 10000000) {
						$mValue = NULL;
						break;
					}
					$mValue = trim($mValue);
					json_decode($mValue, TRUE, 5);
					if (json_last_error()) {
						$mValue = NULL;
					}
					break;
				case 'email';
					if (!preg_match(self::$_pEmail, $mValue)) {
						$mValue = FALSE;
					}
					break;
				default:
					throw new TangoException('unknwon rule type "'.$sType.'"');
					break;
			}

			$_IN[$sKey] = $mValue;
		}

		$_POST = [];
		$_GET = [];
		$_REQUEST = [];
	}
}
