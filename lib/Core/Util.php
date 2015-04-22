<?php
namespace Tango\Core;

class Util {

	// 因为 flock 方法在结束的时候会释放 lock，所以需要另存个地方
	// 虽然在一个脚本里锁多个文件是个很奇怪的用法，但也还顺便支持了
	static $_lFlockPool = [];

	/**
	 * 非阻塞文件锁，可以用于限制并发数量
	 *
	 * @param mixed $sFile 文件名，多个文件的话需要包含 %d 用于 sprintf
	 * @param int $iNum 并发数量，默认1
	 * @static
	 * @access public
	 * @return boolean 是否锁成功
	 */
	public static function flock($sFile = NULL, $iNum = 1) {

		$iNum = max($iNum, 1);
		$iNum = min($iNum, 20);

		if (!$sFile) {
			$sFile = sprintf('/tmp/tango_%s.lock', sha1($_SERVER['SCRIPT_FILENAME']));
		}

		$sKeyPool = sha1($sFile);

		if ($iNum > 1) {

			if (strpos($sFile, '%d') === FALSE) {
				$sFile .= '.%d';
			}

			$lLockFile = array_map(function ($i) use ($sFile) {
				return sprintf($sFile, $i);
			}, range(1, $iNum));

		} else {

			$lLockFile = [$sFile];
		}

		$bLock = FALSE;

		$aPool =& static::$_lFlockPool[$sKeyPool];
		if ($aPool) {
			return $aPool['lock'];
		}

		$aPool = [];

		foreach ($lLockFile as $sLockFile) {

			$iMask = umask(0);
			$hFile = fopen($sLockFile, 'a', 0666);
			umask($iMask);
			if (!$hFile) {
				$bLock = FALSE;
				break;
			}

			if (flock($hFile, LOCK_EX | LOCK_NB)) {
				$bLock = TRUE;
				$aPool['file'] = $hFile;
				break;
			}
		}

		$aPool['lock'] = $bLock;

		return $bLock;
	}

	public static function json($mData, $bPretty = FALSE) {
		$iArg = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		if ($bPretty) {
			$iArg = $iArg | JSON_PRETTY_PRINT;
		}
		return json_encode($mData, $iArg);
	}

	// public convertDigitalUnit(sNum) {{{
	/**
	 * convertDigitalUnit
	 *
	 * 将带有资讯计量单位到数字转成整数，下面是一些转换结果可供参考
	 *
	 * -1.4B = -1
	 * 123 = 123
	 * 9MB = 9437184
	 * 5KiB = 5000
	 * 15P = 16888498602639360
	 * 1.5 K = 1536
	 * -1.2 GiB = -1200000000
	 *
	 * @param mixed $sNum
	 * @static
	 * @access public
	 * @return bool|int
	 */
	public static function convertDigitalUnit($sNum) {
		if (!is_string($sNum)) {
			return FALSE;
		}
		$aMatch = [];
		if (!preg_match('#(\-?\d+(\.\d+)?)\ ?(K|M|G|T|P|G|E|Z|Y)?(iB|B)?$#', $sNum, $aMatch)) {
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
	// }}}
}
