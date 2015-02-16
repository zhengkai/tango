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

			$hFile = fopen($sLockFile, 'a+');
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
}
