<?php
namespace Tango\Core;

require_once __DIR__.'/ext.class.php';

ob_start();

$T =& Tango::$T;
$D =& Tango::$D;
$_IN =& Tango::$IN;

class Tango {

	static public $T = [];
	static public $D = [];
	static public $IN = [];

	static public $_lURLHook = [];

	static protected $_sExt  = 'html';

	static public function init() {

		if (strpos($_SERVER['SCRIPT_NAME'], '..') !== FALSE) {
			die('attack alert');
		}

		register_shutdown_function([__CLASS__, 'tpl']);

		$lNameInfo = pathinfo($_SERVER['SCRIPT_NAME']);
		if ($lNameInfo['extension'] != 'php') {
			die('not a php');
		}

		$lFileExt = explode('.', $lNameInfo['filename'], 20);
		if (count($lFileExt) > 1) {
			array_shift($lFileExt);
			while ($sExt = array_pop($lFileExt)) {
				switch ((string)$sExt) {
					case 'post':
						break;
					default:
						Ext::set($sExt, TRUE);
						break 2;
				}
			}
		}

		// ini_get('output_buffering');
		$sFile = dirname(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'])
			.'/www/'
			.ltrim($_SERVER['SCRIPT_NAME'], '/');

		$_SERVER['SCRIPT_FILENAME'] = $sFile;

		Ext::set('html', TRUE);

		return $sFile;
	}

	static public function tpl() {
		$aExt = Ext::get();
		$s = ob_get_clean();
		if ($s) {
			echo $s;
			return;
		}

		Ext::parse(self::$T, self::$D);
	}
}
