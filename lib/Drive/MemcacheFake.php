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

class MemcacheFake {

	public function __call($sFunc, $lArg) {
		return NULL;
	}

	public function getResultCode() {
		return \Memcached::RES_FAILURE;
	}
}
