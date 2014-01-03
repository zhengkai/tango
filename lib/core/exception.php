<?php
namespace Tango\Core;

Config::setFileDefault('exception', dirname(__DIR__).'/config/exception.php');

class TangoException extends \Exception {

	static protected $_iDepth = 1;
	static protected $_sLastError = '';

	public function __construct($sMessage, $iDepth = 0, $iCode = 0) {

		$iDepth = (int)$iDepth;
		if ($iDepth < 0) {
			$iDepth = 0;
		}
		self::$_iDepth = $iDepth;

		parent::__construct($sMessage, $iCode);
	}

	static public function getLastError() {
		return self::$_sLastError;
	}

	static public function register() {
		set_exception_handler([__CLASS__, 'handler']);
		set_error_handler([__CLASS__, 'errorHandler']);
	}

	static public function handler(\Exception $e, $bSend = TRUE) {
		$s = "Uncaught exception: ".$e->getMessage();

		Log::debug('error', 'catch');

		$lTrace = $e->getTrace();

		$aTrace = current($lTrace);

		if (get_class($e) === __CLASS__) {
			if (!self::$_iDepth && !empty($lTrace[0]['class'])) {
				$sClass = $lTrace[0]['class'];
				foreach ($lTrace as $aRow) {
					if (empty($aRow['class']) || $aRow['class'] !== $sClass) {
						$aTrace = $aPrev;
						break;
					}
					$aPrev = $aRow;
				}
			} else {
				$aSelect =& $lTrace[self::$_iDepth - 1];
				if ($aSelect) {
					$aTrace = $aSelect;
				}
			}
		}

		$aTrace += [
			'file' => '',
			'line' => 0,
		];

		$sHash = hash('crc32', $_SERVER["REMOTE_PORT"]."\n".microtime(TRUE)."\n".$e->getMessage()."\n".Tango::getAI());

		$sHashType = hash('crc32', json_encode($aTrace, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

		$aTrace += [
			'type' => '',
			'class' => '',
		];

		$aConfig = Config::get('exception');
		if ($aConfig['timezone']) {
			$oTime = new DateTime('now', $aConfig['timezone']);
			$sTime = $oTime->format($aConfig['time_format']);
		} else {
			$sTime = date($aConfig['time_format']);
		}

		$sFunc = $aTrace['class'].$aTrace['type'].$aTrace['function'];
		$iArgLenLimit = 80 - 3 - strlen($sFunc);
		if ($iArgLenLimit < 50) {
			$iArgLenLimit = 50;
		}

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
			.$sFunc.'('.$sArg.")\n"
			.'on file '.$aTrace['file'].' ['.$aTrace['line'].']'."\n"
			.'uri '.$_SERVER['REQUEST_URI'];

		self::$_sLastError = $s;

		try {
			Page::error('http500');
		} catch(\Exception $e) {}

		if ($bSend) {
			error_log("\n\n".$s, 3, ini_get('error_log'));
		}
	}

	static public function errorHandler($iError, $sMsg, $sFile, $sLine) {
		self::handler(new TangoException($sMsg, 2), FALSE);
		return FALSE;
	}
}
