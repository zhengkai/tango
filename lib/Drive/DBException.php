<?php
namespace Tango\Drive;

class DBException extends \Tango\Core\TangoException {

	public function getError() {
		$lTrace = $this->getTrace();
		$aError =& $lTrace[0]['args'][0];
		if (!is_array($aError)) {
			return FALSE;
		}
		return array_combine(
			['SQLSTATE', 'id', 'msg'],
			$aError
		);
	}
}
