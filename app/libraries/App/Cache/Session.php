<?php
namespace App\Cache {
	use App\System as Sys;
	use App\Cache\AdapterInterface;

	/**
	 * 基于Session的缓存类。
	 */
	class Session implements AdapterInterface {
		/**
		 * 会话实例。
		 */
		protected static $session = null;
		
		/**
		 * 获取缓存在会话中的数据。
		 * @param string $hashKey 用于存放$hashVal的标识符。
		 * @param string $hashVal 用来标识缓存内容的Hash值，当$hashKey存放的旧的$hashVal与新的$hashVal不相等的时候，旧的$hashVal所代表的内容
		 * 就会被清除掉，而新的$hashVal所标识的内容就会被缓存下来。
		 * @param callable $callback 获取新数据的回调函数，函数原型为：mixed callback($params)。
		 * @param mixed $params 传递给获取新数据的回调函数的参数。
		 * @param integer $ttl 缓存生命期秒数(未被使用)。
		 * @return mixed
		 */
		public static function get($hashKey, $hashVal, $callback, $params = null, $ttl = null) {
			if (empty(self::$session)) {
				self::$session = Sys::getInstance()->getDI()->getShared('session');
			}
			
			// 获取缓存的数据。
			if (isset(self::$session[$hashKey])) {
				if (self::$session[$hashKey] == $hashVal) {
					// 返回缓存的数据。
					return self::$session[$hashVal];
				}
				else {
					// 清除掉以前Hash值代表的数据。
					$oldHashVal = self::$session[$hashKey];
					unset(self::$session[$oldHashVal]);
					self::$session[$hashKey] = null;
				}
			}
			
			// 获取Hash值对应的数据。
			if (!is_callable($callback)) {
				Sys::throwException('获取新数据的回调函数参数无效');
			}
			$newData = $callback($params);
			
			// 缓存新的数据。
			self::$session[$hashVal] = $newData;
			
			// 设置新的Hash值。
			self::$session[$hashKey] = $hashVal;
			
			return $newData;
		}
		
		/**
		 * 删除指定键的缓存数据(未被使用)。
		 * @param $string $key
		 * @return void
		 */
		public static function delete($key) {
		}
	}
}