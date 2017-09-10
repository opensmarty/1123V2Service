<?php
namespace App\Cache {
	use App\System as Sys;
	use App\Cache\AdapterInterface;

	/**
	 * 基于APC的缓存类。
	 */
	class APC implements AdapterInterface {
		/**
		 * 默认生命期秒数。
		 */
		const DEFAULT_TTL = 604800;
		
		/**
		 * 缓存键不存在。
		 */
		const KEY_NOT_EXISTS = 0;
		
		/**
		 * 缓存数据已到期。
		 */
		const KEY_DATA_EXPIRES = 1;
		
		/**
		 * 获取指定键的缓存数据，当指定键的缓存不存在或已失效时，它就会执行获取新数据的回调函数，以存储或刷新指定键的缓存数据，默认生命期为一周。
		 * @param string $key 缓存键。
		 * @param integer $mtime 缓存数据源的最新修改时间，用于决定缓存的数据是否已过期，当然也可以通过指定一个负值来表示相对于上一次最新修改时间的间隔值，
		 * 如上一次最新修改时间是x，现$mtime指定为-i，则判断缓存数据是否已过期的算式为 (time()-x) >= +i，用此项功能可间隔性的刷新缓存数据。
		 * @param callable $callback 获取新数据的回调函数，函数原型为：mixed callback($flag, $params)，$flag为常量 KEY_NOT_EXISTS、KEY_DATA_EXPIRES 值之一。
		 * @param mixed $params 传递给获取新数据的回调函数的参数。
		 * @param integer $ttl 缓存生命期秒数，不指定时取常量DEFAULT_TTL。
 		 * @return mixed
		 */
		public static function get($key, $mtime, $callback, $params = null, $ttl = null) {
			// 获取指定键的缓存数据。
			$key = $_SERVER['DOCUMENT_ROOT'] . $key;
			if (!apc_exists($key)) {
				$flag = self::KEY_NOT_EXISTS;
			}
			else {
				$data = apc_fetch($key);
				if ($mtime < 0) {
					if ((time() - $data['mtime']) >= -$mtime) {
						$mtime = time();
					}
					else {
						$mtime = $data['mtime'];
					}
				}
				if ($data['mtime'] >= $mtime) {
					return $data['value'];
				}
				$flag = self::KEY_DATA_EXPIRES;
			}
			
			// 刷新指定键的缓存数据。
			if (!is_callable($callback)) {
				Sys::throwException('获取新数据的回调函数参数无效');
			}
			$value = $callback($flag, $params);
			if (is_null($ttl) || $ttl < 0) {
				$ttl = self::DEFAULT_TTL;
			}
			apc_store($key, array(
				'mtime' => $mtime, 
				'value' => $value
			), $ttl);
			
			return $value;
		}
		
		/**
		 * 删除指定键的缓存数据。
		 * @param $string $key
		 * @return void
		 */
		public static function delete($key) {
			apc_delete($key);
		}
	}
}