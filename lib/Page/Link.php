<?php
namespace Tango\Page;

class Link {

	protected $_aArg = [];
	protected $_sURI = '';

	public function __construct($aArg = [], $sURI = '') {
		$this->_aArg = $aArg;
		$this->_sURI = $sURI;
	}

	public function make($aArg = []) {
		$aArg += $this->_aArg;
		$sLink = '';
		if ($this->_sURI) {
			$sLink = $this->_sURI.'?';
		}

		$sArg = http_build_query($aArg, '', '&');

		return $sLink.$sArg;
	}

	public function makePage($iPage, $sKey = 'page') {
		return $this->make([$sKey => $iPage]);
	}
}
