<?php
namespace App {
	use App\System as Sys;
	use App\Session\AdapterInterface;

	/**
	 * 应用程序安全类。
	 */
	class Security {
		/**
		 * 安全令牌键。
		 */
		protected static $tokenKey = '_securityToken';
		
		/**
		 * 会话实例。
		 */
		protected static $session = null;
		
		/**
		 * 获取底层使用的会话实例。
		 * @return App\Session\AdapterInterface
		 */
		protected static function getSession() {
			if (empty(self::$session)) {
				self::$session = Sys::getInstance()->getDI()->getShared('session');
			}
			return self::$session;
		}
		
		/**
		 * 获取避免重防攻击的安全令牌。
		 * @return string
		 */
		public static function getToken() {
			$session = self::getSession();
			$tokenKey = self::$tokenKey;
			if (!isset($session[$tokenKey])) {
				$session[$tokenKey] = md5(microtime());
			}
			return $session[$tokenKey];
		}
		
		/**
		 * 清除避免重防攻击的安全令牌。
		 * @return void
		 */
		public static function clearToken() {
			$session = self::getSession();
			$tokenKey = self::$tokenKey;
			unset($session[$tokenKey]);
		}
	}
}
