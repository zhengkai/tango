<?php
namespace Tango\Addon;

use Tango\Core\TangoException;
use Tango\Core\Page;

/**
 * Session 已登录用户的判定/个人信息
 */
class Session {

	const COOKIE_NAME = 'tango_session';
	const RECORD_MIN_SEC = 300;
	protected static $_iUser = FALSE;
	protected static $_bAuth = NULL;
	protected static $_bSetSession = TRUE;

	protected static $_bAdmin;

	public static function isAdmin() {
		if (static::$_bAdmin === NULL) {
			static::$_bAdmin = ($_ENV['REMOTE_ADDR'] === '::ffff:127.0.0.1');
		}
		return static::$_bAdmin;
	}

	public static function auth() {
		$bAuth =& self::$_bAuth;
		if ($bAuth !== NULL) {
			return $bAuth;
		}

		$bAuth = FALSE;

		// Cookie 完整性/防篡改校验
		$sCookie =& $_COOKIE[self::COOKIE_NAME];
		if (!is_string($sCookie)) {
			return FALSE;
		}
		$iLength = strlen($sCookie);
		if ($iLength < 45 || $iLength > 80) {
			return FALSE;
		}
		$sCheckHash = substr($sCookie, 0, 40);
		$sCookie = substr($sCookie, 40);
		if ($sCheckHash != self::_cookieHash($sCookie)) {
			return FALSE;
		}

		$aCookie = array_combine(
			['id', 'session', 'time'],
			explode(',', $sCookie, 3) + [0, 0, 0]
		);
		$aCookie = array_map('intval', $aCookie);

		$aUser = Passport::id($aCookie['id']);
		if (!$aUser || !empty($aUser['status'])) {
			return FALSE;
		}

		if ($_SERVER['REQUEST_TIME'] - $aUser['date_active'] > self::RECORD_MIN_SEC) {
			self::updateSession($aCookie['id'], $aCookie['session']);
		}

		self::$_iUser = $aCookie['id'];
		return $bAuth = TRUE;
	}

	protected static function _cookieHash($sCookie) {

		$sUserAgent = preg_replace('#\d+#', '', $_SERVER['HTTP_USER_AGENT']);
		// 用去了版本号的 useragent 作为 salt 的一部分

		$sUserAgent = str_replace('AlexaToolbar/alxg-.', '', $sUserAgent);
		// TODO: Alexa 插件很恶心，打开不同路径时发送的 UA 不一样

		$sUserAgent = trim($sUserAgent);

		return sha1($sCookie . Page::getConfig()['salt'] . $sUserAgent);
	}

	/**
	 * 生成流水号
	 */
	public static function genID() {

		$oDB = \Tango\Drive\DB::getInstance('passport');
		return $oDB->genAI('session_id_generator');
	}

	/**
	 * 更新 date_active
	 */
	protected static function _updateActive() {

		if (!self::$_bAuth) {
			throw new TangoException('no session');
		}

		$oDB = \Tango\Drive\DB::getInstance('passport');
		return $oDB->exec($sQuery);
	}

	public static function setSession($iUser) {

		self::$_iUser = $iUser;
		self::$_bAuth = TRUE;

		self::updateSession($iUser);
	}

	public static function updateSession(int $iUser, int $iSession = 0) {

		if (!self::$_bSetSession) {
			return FALSE;
		}
		self::$_bSetSession = FALSE;

		if ($iSession < 1) {
			$iSession = self::genID();
		}

		$oDB = \Tango\Drive\DB::getInstance('passport');
		$sQuery = 'UPDATE user '
			.'SET session_id = '.$iSession.', '
			.'date_active = ' . $_SERVER['REQUEST_TIME'] . ' '
			.'WHERE user_id = ' . $iUser . ' '
			.'AND date_active < ' . $_SERVER['REQUEST_TIME'];
		$oDB->exec($sQuery);

		$aCookie = [
			$iUser,
			$iSession,
			$_SERVER['REQUEST_TIME'],
		];

		$sCookie = implode(',', $aCookie);
		$sCookie = self::_cookieHash($sCookie).$sCookie;

		self::_setCookie($sCookie);
	}

	public static function cleanCookie() {

		self::$_bAuth = FALSE;
		self::_setCookie(FALSE);
	}

	protected static function _setCookie(string $sValue) {

		if (PHP_SAPI === 'cli' || headers_sent()) {
			return FALSE;
		}

		$_COOKIE[self::COOKIE_NAME] = $sValue;

		$sDomain = \Tango\Core\Page::getConfig()['cookie_domain'];

		setcookie(self::COOKIE_NAME, $sValue ?: '', $sValue ? 2147483647 : 0, '/', $sDomain);
		return TRUE;
	}

	/**
	 * 当前用户ID
	 *
	 * 调用前一定要先用过 Session::gate() 或者 Session::auth()
	 */
	public static function id() {

		if (!self::$_bAuth) {
			throw new TangoException('no session');
		}
		return self::$_iUser;
	}

	/**
	 * 仅限登录用户，没有登录就做页面跳转，通常用在页头
	 */
	public static function gate(): bool {

		if (Session::auth()) {
			return FALSE;
		}

		$sReturn = '';
		if (!$_POST) {
			$sReturn = '?return=' . urlencode($_SERVER["REQUEST_URI"]);
		}
		Page::jump('/passport/login' . $sReturn);
		return TRUE;
	}
}
