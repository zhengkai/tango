<?php
function j($sMessage, $sType = 'log') {
	Log::debug($sType, $sMessage, FALSE);
}

function json($mData) {
	return json_encode($mData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function jsonf($mData) {
	return json_encode($mData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

function dump() {
	$sOut = '';
	foreach (func_get_args() as $mVar) {
		if (is_array($mVar)) {
			$sDump = print_r($mVar, TRUE);
		} else {
			$sDump = var_export($mVar, TRUE);
		}
		$sDump = preg_replace("/\\)\n\n/", ")\n", $sDump);
		//$sDump = preg_replace("/Array\n(\\s+)(/", ")\n", $sDump);
		if (PHP_SAPI !== "cli") {
			$sDump = trim($sDump);
			$sDump = Tango\Page\HTML::escape($sDump);
			$sDump = str_replace(["\n", "\r\n", "\r"], '<br />', $sDump);
			$sDump = str_replace('  ', '&#160; ', $sDump);
			$sDump = '<pre style="cursor: default; text-align: left; font-weight: normal; font-size: 14px; overflow: auto; max-height: 600px; font-family: &#034;Droid Sans Mono&#034;, monospace; line-height: 22px; margin: 4px auto; max-width: 940px; min-width: 300px; border: 1px solid teal; background-color: #efe; color: teal; padding: 9px;">'
				. "\n" . $sDump . "\n"
				. '</pre>';
		}
		$sOut .= $sDump;
	}
	echo $sOut;
}

function time_diff_str(int $t1, int $t2 = 0): string {
	if ($t1 > $t2) {
		list($t1, $t2) = [$t2, $t1];
	}
	$t1 = (new DateTime())->setTimestamp($t1);
	$t2 = (new DateTime())->setTimestamp($t2);
	$interval = $t1->diff($t2);

	if ($t1 > $t2) {
	}

	$sReturn = '';
	$i = 0;
	foreach (['y' => 'Year', 'm' => 'Month', 'd' => 'Day', 'h' => 'Hour', 'i' => 'Minute', 's' => 'Second'] as $sKey => $sName) {

		$iNum = $interval->{$sKey};

		if (!$iNum && !$i) {
			continue;
		}
		$i++;

		$sReturn .= $iNum . ' ' . $sName . ($iNum ? 's' : '') . ' ';
		if ($i > 1) {
			break;
		}
	}
	$sReturn = trim($sReturn);
	if (!$sReturn) {
		$sReturn = '0 Second';
	}
	return $sReturn;
}
