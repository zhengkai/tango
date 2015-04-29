<?php
/**
 * This file is part of the Tango Framework.
 *
 * (c) Zheng Kai <zhengkai@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tango\Drive;

/**
 * 跟 \Tango\Core\TangoException 类似，差别在于能返回 PDO 错误代码
 *
 * @package
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class DBException extends \Tango\Core\TangoException {

	/**
	 * 返回 PDO 错误代码
	 *
	 * @access public
	 * @return boolean|array
	 */
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
