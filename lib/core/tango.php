<?php
namespace Tango\Core;

Config::setFileDefault('tango', dirname(__DIR__).'/config/tango.php');

class Tango {

	static public $T = [];
	static public $D = [];
	static public $IN = [];

	static public $_lURLHook = [];

	static protected $_sExt  = 'html';

	static protected $_bInit = FALSE;

	static protected $_bOB = TRUE; // output buffering
	static protected $_iAI = 0;

	static public $_bDebug;
	static public $_sScriptID;

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
						if (self::$_bOutbuffer) {
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
			register_shutdown_function([__CLASS__, 'tpl']);
			Page::set('html', TRUE);
			ob_start();
		}

		return $sFile;
	}

	static public function tpl() {
		$s = ob_get_clean();
		if ($s) {
			echo $s;
			return;
		}

		Page::parse();
	}

	static public function getAI() {
		return ++self::$_iAI;
	}
}
