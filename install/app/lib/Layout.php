<?php
class Layout extends Tango\Page\Layout {

	protected $_sLayout = 'Fixed';

	public function run() {

		$sLayout = $this->_sLayout ?: 'Single';

		$sFile = dirname(__DIR__) . '/layout/' . $sLayout . '.php';

		if (is_file($sFile)) {
			require $sFile;
			return TRUE;
		}

		$aCall = [$this, '_run' . $sLayout];
		if (!is_callable($aCall)) {
			throw new \Exception('unknown layout "' . $this->_sLayout . '"');
		}
		return $aCall();
	}
}
