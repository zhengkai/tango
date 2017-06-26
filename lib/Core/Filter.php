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
	 * 工作方法
	 *
     * @param string $sMethod "POST" 或 "GET"
     * @param array $lRule 包含要获取的参数的 key 和实际类型，并进行转换
	 *     字符串会被 utf8 编码过滤，不符合 utf8 编码的字符被丢弃
     *     需要注意默认的 int 不允许有负值，否则请使用 signedInt
	 * @throws TangoException
	 */
	public static function run($sMethod = "GET", $lRule = []): void {

		if (self::$_bCheck) {
			throw new TangoException('filter checked');
		}
		self::$_bCheck = TRUE;

		switch ((string)$sMethod) {

			case 'POST':
				if (!static::checkReferer()) {
					return;
				}
			case 'POST_NO_REF_CHECK':
				$aParm =& $_POST;
				break;

			case 'GET':
				$aParm =& $_GET;
				break;

			case 'POST_JSON':
				$aParm = json_decode(file_get_contents('php://input'), TRUE);
				if (!is_array($aParm)) {
					throw new TangoException('wrong POST JSON');
				}
				break;

			default:
				throw new TangoException('unknown HTTP method "'.$sMethod.'"');
				break;
		}

		$_IN =& Page::$IN;

		foreach ($lRule as $sKey => $sType) {
			$mValue =& $aParm[$sKey];
			if (is_array($mValue) && $sType !== 'array') {
				$_IN[$sKey] = FALSE;
				continue;
			}

			switch ($sType) {
				case 'int':
					$mValue = str_replace(
						['１', '２', '３', '４', '５', '６', '７', '８', '９', '０'],
						[1, 2, 3, 4, 5, 6, 7, 8, 9, 0],
						$mValue
					);
					$mValue = (int)$mValue;
					if ($mValue < 1) {
						$mValue = 0;
					}
					break;
				case 'str':
					$mValue = iconv('UTF-8', 'UTF-8//IGNORE', trim($mValue));
					if (strlen($mValue) > 1024) {
						$mValue = '';
					}
					break;
				case "hex":
					if (!preg_match('#^[0-9a-f]{0,1024}$#', $mValue) || ((strlen($mValue) % 2) !== 0)) {
						$mValue = FALSE;
					}
					break;
				case 'longStr':
					$mValue = iconv('UTF-8', 'UTF-8//IGNORE', trim($mValue));
					break;
				case 'time':
					$sValue = iconv('UTF-8', 'UTF-8//IGNORE', trim($mValue));
					$mValue = strtotime($sValue) ?: 0;
					break;
				case 'array':
					if (!is_array($mValue)) {
						$mValue = [$mValue];
					}
					break;
				case 'bin':
					break;
				case 'signedInt':
					$mValue = (int)$mValue;
					break;
				case 'bool':
					$mValue = (bool)$mValue;
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
					$mValue = filter_var($mValue, FILTER_VALIDATE_EMAIL);
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

	public static function checkReferer(): bool {
		return TRUE;
	}
}
