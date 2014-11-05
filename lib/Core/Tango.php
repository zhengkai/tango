<?php
namespace Tango\Core;

Config::setFileDefault('tango', dirname(__DIR__).'/Config/tango.php');

class Tango {

	static public $T = [];
	static public $D = [];
	static public $IN = [];

	static public $_lURLHook = [];

	static protected $_sExt  = 'html';

	static protected $_bTplCalled = FALSE;

	static protected $_bInit = FALSE;

	static protected $_fTimeController;

	static protected $_bShutdown = FALSE;

	static protected $_bOB = TRUE; // output buffering
	static protected $_iAI = 0;

	static protected $_bDebug;
	static protected $_sScriptID;

	static protected $_lErrorStopCode = [
		E_ERROR,
		E_CORE_ERROR,
		E_COMPILE_ERROR,
		E_USER_ERROR,
		E_RECOVERABLE_ERROR,
	];

	static public function getTimeController() {
		return self::$_fTimeController;
	}

	static public function getScriptID() {
		if (!is_null(self::$_sScriptID)) {
			return $_sScriptID;
		}
		self::$_sScriptID = uniqid().sprintf('%07x', mt_rand(0, 0xfffffff));
		return self::$_sScriptID;
	}

	static public function isDebug() {
		if (!is_null(self::$_bDebug)) {
			return self::$_bDebug;
		}
		$aConfig = Config::get('tango');
		self::$_bDebug = $aConfig['debug']['enable']
			&& in_array($_SERVER["REMOTE_ADDR"], $aConfig['debug']['allow_ip']);
		return self::$_bDebug;
	}

	static public function isInit() {
		return self::$_bInit;
	}

	static public function init() {

		if (self::$_bInit) {
			die('ready inited');
		}
		self::$_bInit = TRUE;

		if (strpos($_SERVER['SCRIPT_NAME'], '..') !== FALSE) {
			die('attack alert');
		}

		$lNameInfo = pathinfo($_SERVER['SCRIPT_NAME']);
		if ($lNameInfo['extension'] != 'php') {
			die('not a php');
		}

		$lFileExt = explode('.', $lNameInfo['filename'], 20);
		if (count($lFileExt) > 1) {
			array_shift($lFileExt);
			while ($sExt = array_pop($lFileExt)) {
				switch ((string)$sExt) {
					case 'unob':
						self::$_bOB = FALSE;
						break;
					case 'post':
						break;
					default:
						if (self::$_bOB) {
							Page::set($sExt, TRUE);
						}
						break 2;
				}
			}
		}

		define('SITE_ROOT', dirname(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file']));

		$sFile = SITE_ROOT.'/www'.$_SERVER['SCRIPT_NAME'];

		$_SERVER['SCRIPT_FILENAME'] = $sFile;

		if (self::$_bOB) {
			register_shutdown_function([__CLASS__, 'shutdown']);
			ob_start();
		}

		self::_start();

		if (self::$_bOB) {
			self::_end();
		}
	}

	static protected function _start() {

		$T =& self::$T;
		$D =& self::$D;
		$_IN =& self::$IN;

		require $_SERVER['SCRIPT_FILENAME'];

		if (!self::$_fTimeController) {
			self::$_fTimeController = microtime(TRUE);
		}
	}

	static public function _end() {

		if ($aError = self::getStopError()) {
			ob_clean();
			TangoException::handler(new \ErrorException($aError['message'], 0, 1, $aError['file'], $aError['line']), FALSE);
		} else {
			$s = ob_get_clean();
			if ($s) {
				echo $s;
				self::$_bShutdown = TRUE;
				return;
			}
		}
		Page::parse();
	}

	static public function getStopError() {
		$aError = error_get_last();
		if (!$aError) {
			return FALSE;
		}
		if (!self::isStopError($aError['type'])) {
			return FALSE;
		}
		return $aError;
	}

	static public function isStopError($iError) {
		return in_array($iError, self::$_lErrorStopCode);
	}

	static public function shutdown() {

		if (self::$_bShutdown) { // run once only
			return FALSE;
		}
		self::$_bShutdown = TRUE;

		self::_end();
	}

	static public function getAI() {
		return ++self::$_iAI;
	}
}
