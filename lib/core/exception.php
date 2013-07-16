<?php
namespace Tango\Core;

Config::setFileDefault('exception', dirname(__DIR__).'/config/exception.php');

class TangoException extends \Exception {

	public function __construct($sMessage, $iDepth = NULL, $iCode = 0) {
		parent::__construct($sMessage, $iCode);
	}

	static public function register() {
		set_exception_handler([__CLASS__, 'handler']);
		// set_error_handler([__CLASS__, 'errorHandler']);
		file_put_contents('/tmp/er', __FUNCTION__."\n", FILE_APPEND);
	}

	static public function handler(\Exception $e) {
		$s = "Uncaught exception: ".$e->getMessage();
		file_put_contents('/tmp/er', __FUNCTION__."\n", FILE_APPEND);
		error_log($s);
	}

	static public function errorHandler($iError, $sMsg, $sFile, $sLine) {
		file_put_contents('/tmp/er', __FUNCTION__."\n", FILE_APPEND);
		// throw new \ErrorException($sMsg, 0, $iError, $sFile, $sLine);
		throw new TangoException($sMsg);
		return false;
	}
}
