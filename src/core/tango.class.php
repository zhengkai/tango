<?php
namespace Tango\Core;

require_once __DIR__.'/ext.class.php';

$T =& Tango::$T;
$D =& Tango::$D;
$_IN =& Tango::$IN;

class Tango {

	static public $T = [];
	static public $D = [];
	static public $IN = [];

	static public $_lURLHook = [];

	static protected $_sExt  = 'html';

	static protected $_bInit = FALSE;

	static protected $_bOB = TRUE; // output buffering

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
							Ext::set($sExt, TRUE);
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
			Ext::set('html', TRUE);
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
		Ext::parse(self::$T, self::$D);
	}
}
