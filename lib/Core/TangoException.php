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
 * 异常
 *
 * 跟原始 Exception 的区别在于有“深度”的概念，以便更及时的判断错误内容
 * 例如 MySQL 报 sql 语句为空，我们更想知道是哪里发送空 sql，
 * 这个位置信息其实是在 trace 的第三行而非第一行
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class TangoException extends \Exception {

	/** 报告深度 */
	protected static $_iDepth = 1;

	/** 用于显示的报错信息 */
	protected static $_sLastError = '';

	/**
	 * __construct
	 *
	 * @param string $sMessage 报错信息
	 * @param int $iDepth 深度
	 * @param int $iCode 错误代码
	 * @access public
	 */
	public function __construct($sMessage, $iDepth = 0, $iCode = 0) {
		self::$_iDepth = max($iDepth, 0);
		parent::__construct($sMessage, $iCode);
	}

	/**
	 * 最后错误信息
	 *
	 * @static
	 * @access public
	 * @return string
	 */
	public static function getLastError() {
		return self::$_sLastError;
	}

	/**
	 * set_error_handler
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function register() {
		set_exception_handler([__CLASS__, 'handler']);
		set_error_handler([__CLASS__, 'errorHandler']);
	}

	/**
	 * Exception Handler
	 *
	 * @param \Exception $e
	 * @param boolean $bSend
	 * @static
	 * @access public
	 * @return string
	 */
	public static function handler(\Exception $e, $bSend = TRUE) {

		static $_sTimeFormat;
		if ($_sTimeFormat === NULL) {
			Log::getConfig()['time_format'];
		}

		$aTrace = [];

		if (is_a($e, __CLASS__)) {

			$lTrace = $e->getTrace();
			$aTrace = current($lTrace);
			if (!self::$_iDepth && !empty($lTrace[0]['class'])) {
				$sClass = $lTrace[0]['class'];
				$aPrev = null;
				foreach ($lTrace as $aRow) {
					if (empty($aRow['class']) || $aRow['class'] !== $sClass) {
						$aTrace = $aPrev;
						break;
					}
					$aPrev = $aRow;
				}
			} else {
				$aSelect =& $lTrace[self::$_iDepth ? self::$_iDepth - 1 : 0];
				if ($aSelect) {
					$aTrace = $aSelect;
				}
			}

		} else {

			$aTrace['file'] = $e->getFile();
			$aTrace['line'] = $e->getLine();
		}

		$aTrace += [

			'file' => '',
			'line' => 0,

			'type'   => '',
			'class'  => '',
			'function' => '',

			'args'   => '',
		];

		$fTime = microtime(TRUE);

		$bCli = PHP_SAPI === 'cli';

		$sHash = bin2hex(random_bytes(4));

		$sHashType = hash('crc32', json_encode($aTrace, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

		$sTime = date($_sTimeFormat, $fTime);
		$sTime .= substr(sprintf('%.03f' ,$fTime), -4);

		$sFunc = $aTrace['class'].$aTrace['type'].$aTrace['function'];
		$iArgLenLimit = max(80 - 3 - strlen($sFunc), 50);

		$sArg = '';
		if ($aTrace['args']) {

			$bFirst = TRUE;
			foreach ($aTrace['args'] as $mArg) {
				if ($bFirst) {
					$bFirst = FALSE;
				} else {
					$sArg .= ', ';
				}
				$sArg .= json_encode($mArg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
				$iLength = strlen($sArg);
				if ($iLength > $iArgLenLimit) {
					$sArg = mb_substr($sArg, 0, $iArgLenLimit - 4).' ...';
					break;
				}
			}
		}

		$sMsg = trim($e->getMessage());
		$sMsg = '  '.str_replace("\n", "\n  ", $sMsg);

		$s = '['.$sTime.'] ['.$sHash.'.'.$sHashType.']'."\n\n"
			.$sMsg."\n\n"
			.($sFunc ? $sFunc.'('.$sArg.")\n" : '')
			.'on file '.$aTrace['file'].' ['.$aTrace['line'].']'
			.($bCli ? '' : "\n".'uri '.$_SERVER['REQUEST_URI']);

		self::$_sLastError = $s;

		if ($bSend) {
			error_log("\n".$s."\n", 3, ini_get('error_log'));
		}
		return $s;
	}

	/**
	 * Error Handler
	 *
	 * @param integer $iError
	 * @param string $sMsg
	 * @param string $sFile
	 * @param string $sLine
	 * @static
	 * @access public
	 * @return bool
	 */
	public static function errorHandler($iError, $sMsg, $sFile, $sLine) {
		if (Tango::isStopError($iError)) {
			self::handler(new TangoException($sMsg, 2), FALSE);
		}
		return FALSE;
	}
}
