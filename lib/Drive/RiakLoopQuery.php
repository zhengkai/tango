<?php
namespace Tango\Drive;

// http://docs.basho.com/riak/latest/dev/using/2i/#Querying

class RiakLoopQuery {

	protected $_oRiak;
	protected $_sBucket;
	protected $_sIndex;
	protected $_mVal;

	protected $_iLimit;
	protected $_sContinuation;

	public function __construct(Riak $oRiak, $sBucket, $sIndex, $mVal, $iLimit) {

		$this->_oRiak = $oRiak;
		$this->_sBucket = $sBucket;
		$this->_sIndex = $sIndex;
		$this->_mVal = $mVal;
		$this->_iLimit = $iLimit;
	}

	public function hasMore() {
		return $this->_sContinuation !== FALSE;
	}

	public function more() {

		if (!$this->hasMore()) {
			return [];
		}

		$aArg = [
			'max_results' => $this->_iLimit,
		];
		if ($this->_sContinuation) {
			$aArg['continuation'] = $this->_sContinuation;
		}

		$aReturn = $this->_oRiak->queryIndex($this->_sBucket, $this->_sIndex, $this->_mVal, $aArg);
		if (!is_array($aReturn)) {
			$this->_sContinuation = FALSE;
			return [];
		}
		$aReturn += [
			'keys' => [],
			'continuation' => NULL,
		];

		if ($aReturn['continuation']) {
			$this->_sContinuation = $aReturn['continuation'];
		} else {
			$this->_sContinuation = FALSE;
		}
		return $aReturn['keys'] ?: [];
	}
}
