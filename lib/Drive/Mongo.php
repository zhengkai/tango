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
 * MongoDB 基础类
 *
 * 主要解决问题：多次更改，一次读写
 *
 * @package
 * @author Zheng Kai <zhengkai@gmail.com>
 */
class Mongo {

	/** 数组连接池 */
	protected static $_lPoolDB = [];

	/** 内存数组缓存 */
	protected static $_lPoolData = [];

	/** _id 的类型，"int" "str" 或者 "bin" */
	protected $_sKeyType = 'int';
}
