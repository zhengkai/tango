<?php
namespace Tango\Addon;

class Pagination {

	protected $_iNumberPerPageDefault = 30;

	protected static $_iPageNowDefault;

	protected $_iCount;
	protected $_iNumberPerPage;
	protected $_iPageNow;
	protected $_iPageMax;
	protected $_iOffset;
	protected $_iLimit;
	protected $_aPageSlice;
	protected $_bReverse;

	protected $_aPublicName = [
		'iPageNow',
		'iPageMax',
		'iNumberPerPage',
		'iCount',
		'aPageSlice',
		'iOffset',
		'iLimit',
	];

	protected static $_aTplDefault = [
		'href'    => '?page=%d',
		'href_firstpage'  => NULL,
		'outer'   => '<ul class="pagination">%s</ul>',
		'outer_when_null' => '',
		'link'    => '<li><a href="%s">%s</a></span>',
		'current' => '<li class="active"><a href="%s">%s</a></span>',
		'dot'     => '<li class="dot"><span>...</span></li>',
		'number'  => '%02d',
	];

	protected $iOffset = 2;
	protected $iBorder = 2;

	public function __construct($iCount, $iPageNow = NULL, $iNumberPerPage = NULL) {

		$iNumberPerPage = intval($iNumberPerPage);

		if ($iPageNow === NULL && self::$_iPageNowDefault) {
			$iPageNow = self::$_iPageNowDefault;
		} else {
			$iPageNow = intval($iPageNow);
		}

		if ($iNumberPerPage === NULL) {
			$iNumberPerPage = $this->_iNumberPerPageDefault;
		} else {
			$iNumberPerPage = intval($iNumberPerPage);
			if ($iNumberPerPage < 1) {
				$iNumberPerPage = 1;
			}
		}
		$iPageMax = (int)ceil(
			($iCount < 1)
			? 1
			: ($iCount / $iNumberPerPage)
		);
		if ($iPageNow > $iPageMax) {
			$iPageNow = $iPageMax;
		} else if ($iPageNow < 1) {
			$iPageNow = 1;
		}

		$this->_iCount         = $iCount;
		$this->_iNumberPerPage = $iNumberPerPage;
		$this->_iPageMax       = $iPageMax;

		$this->_iOffset        = ($iPageNow - 1) * $iNumberPerPage;

		$this->_iLimit         =
			($iPageNow == $iPageMax)
			? $iCount - $this->_iOffset
			: $iNumberPerPage;

		$this->setPageNow($iPageNow);
	}

	public function __get($sName) {

		if (!in_array($sName, $this->_aPublicName)) {
			echo 'unknown propertie "'.$sName.'"';
			exit;
		}
		return $this->{'_'.$sName};
	}

	public static function setPageNowDefault($iPageNow) {
		if (!is_numeric($iPageNow)) {
			return FALSE;
		}
		self::$_iPageNowDefault = intval($iPageNow);
	}

	public function sliceArray($lData) {
		return array_slice($lData, $this->_iOffset, $this->_iLimit, TRUE);
	}

	public function setPageNow($iPageNow) {
		$this->_iPageNow = $iPageNow;
	}

	public function getPageNow() {
		return $this->_iPageNow;
	}

	public function slice($iOffset = 2, $iBorder = 2) {
		$this->iOffset = $iOffset;
		$this->iBorder = $iBorder;

		return self::_slice();
	}

	public function _slice() {

		$iOffset = $this->iOffset;
		$iBorder = $this->iBorder;

		$iKeep = $iOffset + $iBorder + 1;
		$iKeepFull = $iKeep + $iOffset;

		$iPageMax = $this->_iPageMax;

		$iMax = ($iOffset * 2) + $iBorder;

		$aBegin = [];
		if ($this->_iPageNow < $iKeep) {
			$aBegin = range(1, $iKeepFull);
		} else if ($iBorder > 0) {
			$aBegin = range(1, $iBorder);
		}

		$aEnd = [];
		if ($this->_iPageNow > ($iPageMax - $iKeep)) {
			$aEnd = range($iPageMax - $iKeepFull + 1, $iPageMax);
		} else if ($iBorder > 0) {
			$aEnd = range($iPageMax - $iBorder + 1, $iPageMax);
		}

		$aBody = range($this->_iPageNow - $iOffset, $this->_iPageNow + $iOffset);
		$aSlice = array_merge($aBegin, $aEnd, $aBody);
		$aSlice = array_unique($aSlice);
		$aSlice = array_filter($aSlice, function($iPage) use ($iPageMax) {
			if ($iPage < 1) {
				return FALSE;
			}
			if ($iPage > $iPageMax) {
				return FALSE;
			}
			return TRUE;
		});

		sort($aSlice);
		$this->_aPageSlice = $aSlice;

		return $aSlice;
	}

	public function getLimit($bOptimize = FALSE, $bOrderASC = FALSE) {

		$sLimit = ' ';

		$sOrder = $bOrderASC ? 'ASC' : 'DESC';
		$sDeOrder = $bOrderASC ? 'DESC' : 'ASC';

		if ($this->_iCount < $this->_iNumberPerPage) { // 如果总数不到一页，直接短路处理
			$sLimit .= $sOrder.' LIMIT '.$this->_iNumberPerPage;
			return $sLimit;
		}

		$iOffset = ($this->_iPageNow - 1) * $this->_iNumberPerPage;
		$iRowCount = $this->_iNumberPerPage;

		if (!$bOptimize || $this->_iCount < 500 || ($this->_iPageNow <= $this->_iPageMax / 2)) {
			$sLimit .= $sOrder;
			$this->_bReverse = FALSE;
		} else {
			$iOffset = $this->_iCount - $iOffset - $this->_iNumberPerPage;
			if ($iOffset < 0) {
				$iRowCount = $this->_iNumberPerPage + $iOffset;
				$iOffset = 0;
			}
			$sLimit .= $sDeOrder;
			$this->_bReverse = TRUE;
		}
		$sLimit .= ' LIMIT '.$iOffset.', '.$iRowCount;

		return $sLimit;
	}

	public function transList($aList) {
		if ($this->_bReverse) {
			$aList = array_reverse($aList, TRUE);
		}
		return $aList;
	}

	public function getHTML($aTpl = []) {
		if (is_string($aTpl)) {
			$aTpl = array('href' => $aTpl);
		}

		$aTpl += self::$_aTplDefault;

		$bOnlyOnePage = $this->_iPageMax == 1;

		if ($bOnlyOnePage) {
			return $aTpl['outer_when_null'];
		}

		$this->_slice();

		$sHTML = '';
		$iPrevNumber = current($this->_aPageSlice);
		$bDot = FALSE;
		foreach ($this->_aPageSlice as $iNumber) {
			if (1 < $iNumber - $iPrevNumber) {
				$sHTML .= $aTpl['dot'];
				$bDot = TRUE;
			}
			$sNumber = sprintf($aTpl['number'], $iNumber);
			if ($iNumber == $this->_iPageNow) {
				$sHTML .= sprintf($aTpl['current'], $iNumber, $sNumber);
			} else {
				if ($iNumber == 1 && $aTpl['href_firstpage']) {
					$sHrefTpl = $aTpl['href_firstpage'];
				} else {
					$sHrefTpl = $aTpl['href'];
				}
				$sHref = sprintf($sHrefTpl, $iNumber);
				$sHTML .= sprintf($aTpl['link'], $sHref, $sNumber);
			}
			$iPrevNumber = $iNumber;
		}

		if ($bDot) {
			$sHTML =
				'<li><a href="'.sprintf($sHrefTpl, 1).'"><i class="fa fa-fw fa-angle-double-left"></i></a></li>'
				.'<li><a href="'.sprintf($sHrefTpl, $this->_iPageNow <= 1? 1 : $this->_iPageNow - 1).'"><i class="fa fa-fw fa-angle-left"></i></a></li>'
				.$sHTML
				.'<li><a href="'.sprintf($sHrefTpl, $this->_iPageNow >= $this->_iPageMax ? $this->_iPageMax : $this->_iPageNow + 1).'#"><i class="fa fa-fw fa-angle-right"></i></a></li>'
				.'<li><a href="'.sprintf($sHrefTpl, $this->_iPageMax).'#"><i class="fa fa-fw fa-angle-double-right"></i></a></li>';
		}

		$sHTML = sprintf($aTpl[$bOnlyOnePage ? 'outer_when_null' : 'outer'], $sHTML);
		return $sHTML;
	}
}
