<?php
namespace Tango\Core;

class Util {
	public static function json($mData, $bPretty = FALSE) {
		$iArg = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		if ($bPretty) {
			$iArg = $iArg | JSON_PRETTY_PRINT;
		}
		return json_encode($mData, $iArg);
	}
}
