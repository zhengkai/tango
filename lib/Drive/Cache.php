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

use Tango\Core\TangoException;
use Tango\Core\Log;

class Cache {

	private static $_iArg = 0;
	private static $_lArgName = [];
	private static $_sArgFormat = '';

	private static $_sMCName;
	private static $_iMCExpire = 86400;

	protected static $_lPool = [];

	public static function get(string ...$lArg) {
		$sKey = static::_buildKey($lArg);

		if (array_key_exists($sKey, static::$_lPool)) {

			Log::debug('cache', $sKey . ' $pool hit');

		} else {

			$mData = [static::$_sMCName, 'get']($sKey);
			if ($mData && [static::$_sMCName, 'getResultCode']() === \Memcached::RES_SUCCESS) {

				Log::debug('cache', $sKey . ' memcache hit');

			} else {

				Log::debug('cache', $sKey . ' _get');
				$lGetArg = array_combine(static::$_lArgName, $lArg);
				$mData = static::_get($lGetArg);
				[static::$_sMCName, 'set']($sKey, $mData, static::$_iMCExpire);
			}
			static::$_lPool[$sKey] = $mData;
		}

		return static::$_lPool[$sKey];
	}

	public static function delete(string ...$lArg) {
		$sKey = static::_buildKey($lArg);
		unset(static::$_lPool[$sKey]);
		[static::$_sMCName, 'delete']($sKey);
	}

	protected static function _buildKey($lArg) {

		if (!static::$_iArg) {
			static::$_iArg = count(static::$_lArgName);
		}

		if (count($lArg) !== static::$_iArg) {
			throw new TangoException('args not match ' . count($lArg) . '/' . static::$_iArg, 3);
		}

		$sKey = vsprintf(static::$_sArgFormat, $lArg);
		if (!preg_match('#^[0-9a-z\-_]+#', $sKey)) {
			throw new TangoException('illegal key name "' . $sKey . '"', 2);
		}

		return $sKey;
	}

	private static function _get($lArg) {
		throw new TangoException('empty _get()');
	}
}
