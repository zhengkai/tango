<?php
namespace Tango\Page;

use \Tango\Core\Config;

Config::setFileDefault('layout', dirname(__DIR__).'/Config/layout.php');

class Layout {

	protected $_sBody = '';
	protected $_sLayout; // 不填表示默认

	public function setBody(string $sBody) {
		$this->_sBody = $sBody;
	}

	public function setLayout(string $sLayout) {
		$this->_sLayout = $sLayout;
	}

	public function run() {
		$aCall = [$this, '_run' . ($this->_sLayout ?: 'None')];
		if (!is_callable($aCall)) {
			throw new \Exception('unknown layout "' . $this->_sLayout . '"');
		}
		return $aCall();
	}

	protected function _runNone() {
		echo $this->_sBody;
		return TRUE;
	}
}
