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

Config::setFileDefault('tango', dirname(__DIR__).'/Config/tango.php');

/**
 * 工具类函数
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class Util {

	/** 限制 flock 的最大数目 */
	protected static $_iFlockMax = 20;

	/** 在一个脚本内生成不重复的数字，计数器 */
	protected static $_iAI = 0;

	/** 临时文件所在目录 */
	protected static $_sTmpPath;

	/**
	 * 因为 flock 方法在结束的时候会释放 lock，所以需要另存个地方，
	 * 虽然在一个脚本里锁多个文件是个很奇怪的用法，但也还顺便支持了
	 */
	static $_lFlockPool = [];

	/**
	 * tango 框架的 tmp 目录
	 *
	 * 可以通过更改 Config::get('tango')['tmp_dir']
	 * 来覆盖系统默认（sys_get_temp_dir()）的目录
	 *
	 * @param null|string $sPath 根据开头是否有 / 来判定是返回原地址，或者加上默认目录再返回
	 * @static
	 * @access public
	 * @return void
	 */
	public static function getTmpPath($sPath = NULL) {

		if (substr($sPath, 0, 1) === '/') {
			return $sPath;
		}

		if (!self::$_sTmpPath) {
			$sTmpPath = Config::get('tango')['tmp_dir'];
			self::$_sTmpPath = rtrim($sTmpPath, '/') . '/';
		}

		return self::$_sTmpPath . $sPath;
	}

	/**
	 * 非阻塞文件锁，可以用于限制并发数量
	 *
	 * @param int $iNum 并发数量，默认1
	 * @param string $sFile 文件名，多个文件的话需要包含 %d 用于 sprintf
	 * @static
	 * @access public
	 * @return boolean 是否锁成功
	 */
	public static function flock($iNum = 1, $sFile = NULL) {

		if (!is_int($iNum) && !is_string($sFile)) {
			// 兼容老版本，当时二者是反的，并且 $sFile 必填
			list($iNum, $sFile) = [$sFile ?: 1, $iNum];
		}

		$iNum = (int)max($iNum, 1);
		$iNum = min($iNum, self::$_iFlockMax);

		if (!$sFile) {
			$sFile = sprintf(
				self::getTmpPath('tango_%s.lock'),
				pathinfo($_SERVER['SCRIPT_FILENAME'])['filename']
			);
		}

		$sKeyPool = sha1($sFile);

		if (strpos($sFile, '%d') === FALSE) {
			if (substr($sFile, -5) === '.lock') {
				$sFile = substr($sFile, 0, -5) . '.%d.lock';
			} else {
				$sFile .= '.%d';
			}
		}

		$lNum = range(1, $iNum);
		shuffle($lNum); // 将锁定文件的文件名顺序打乱，以保证有多个文件时，冲突几率最小
		$lLockFile = array_map(function ($i) use ($sFile) {
			return sprintf($sFile, $i);
		}, $lNum);

		$bLock = FALSE;

		$aPool =& self::$_lFlockPool[$sKeyPool];
		if (!$aPool || empty($aPool['filename'])) {

			$aPool = [
				'handle' => FALSE,
				'filename' => FALSE,
			];

			foreach ($lLockFile as $sLockFile) {

				$iMask = umask(0);
				$hFile = fopen($sLockFile, 'a', 0666);
				umask($iMask);
				if (!$hFile) {
					break;
				}

				if (flock($hFile, LOCK_EX | LOCK_NB)) {
					$aPool['handle'] = $hFile;
					$aPool['filename'] = $sLockFile;
					// 必须把 handle 保存在静态变量里，不然函数执行完 handle 变量被回收，也就解锁了……
					break;
				}
			}
		}

		return $aPool['filename'] ?: FALSE;
	}

	/**
	 * 略调整样式的 json_encode，显示网址和多字节字符更好一些
	 *
	 * @param mixed $mData
	 * @param boolean $bPretty
	 * @static
	 * @access public
	 * @return string
	 */
	public static function json($mData, $bPretty = FALSE) {
		$iArg = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		if ($bPretty) {
			$iArg = $iArg | JSON_PRETTY_PRINT;
		}
		return json_encode($mData, $iArg);
	}

	/**
	 * 将带有资讯计量单位到数字转成整数，下面是一些转换结果可供参考
	 *
	 * -1.4B = -1                 <br>
	 * 123 = 123                  <br>
	 * 9MB = 9437184              <br>
	 * 5KiB = 5000                <br>
	 * 15P = 16888498602639360    <br>
	 * 1.5 K = 1536               <br>
	 * -1.2 GiB = -1200000000     <br>
	 *
	 * @param string $sNum
	 * @static
	 * @access public
	 * @return bool|int
	 */
	public static function convertDigitalUnit($sNum) {
		if (!is_string($sNum)) {
			return FALSE;
		}
		$sNum = strtoupper($sNum);
		$aMatch = [];
		if (!preg_match('#(\-?\d+(\.\d+)?)\ ?(K|M|G|T|P|G|E|Z|Y)?(IB|B)?$#', $sNum, $aMatch)) {
			return FALSE;
		}
		$aMatch += [
			'', '', '', '', 'B',
		];

		$iNum = $aMatch[1];
		$sUnit = $aMatch[3];
		$iUnitBase = $aMatch[4] === 'B' ? 1024 : 1000;

		if ($sUnit) {
			$iUnit = strpos('KMGTPGEZY', $sUnit) + 1;
			$iNum *= pow($iUnitBase, $iUnit);
		}

		return intval(round($iNum));
	}

	/**
	 * 在一个脚本内生成不重复的数字
	 *
	 * @static
	 * @access public
	 * @return int
	 */
	public static function getAI() {
		return ++self::$_iAI;
	}
}
