<?php
namespace Tango\Core;

Config::setFileDefault('exception', dirname(__DIR__).'/config/exception.php');

class TangoException extends \Exception {

	static protected $_iDepth = 1;
	static protected $_sLastError = '';

	public function __construct($sMessage, $iDepth = 1, $iCode = 0) {

		$iDepth = (int)$iDepth;
		if ($iDepth < 1) {
			$iDepth = 1;
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

		$lTrace = $e->getTrace();

		$aTrace = current($lTrace);

		if (get_class($e) === __CLASS__) {
			$aSelect =& $lTrace[self::$_iDepth - 1];
			if ($aSelect) {
				$aTrace = $aSelect;
			}
		}

		$sHash = hash('crc32', $_SERVER["REMOTE_PORT"]."\n".microtime(TRUE)."\n".$e->getMessage()."\n".Tango::getAI());

		$sHashType = hash('crc32', $aTrace['file']."\n".$aTrace['line']);

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
				$sArg = json_encode($mArg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
		throw new TangoException($sMsg);
		return false;
	}
}
