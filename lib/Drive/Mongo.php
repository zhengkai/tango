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
 * Mongo
 *
 * @package Tango
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class Mongo {

	use MongoConnect;
	use MongoBatch;
}
