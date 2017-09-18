<?php
namespace App\Cache {
	/**
	 * 缓存适配器接口。
	 */
	interface AdapterInterface {
		/**
		 * 获取指定键的缓存数据，当指定键的缓存不存在或已失效时，它就会执行获取新数据的回调函数，以存储或刷新指定键的缓存数据。
		 * @param string $keyOrElse 缓存键或其它。
		 * @param integer|string $mtimeOrElse 修改时间或其它。
		 * @param callable $callback 获取新数据的回调函数，函数原型由适配器自定。
		 * @param mixed $params 传递给获取新数据的回调函数的参数。
		 * @param integer $ttl 缓存生命期秒数，不指定时默认值由适配器自定。
 		 * @return mixed
		 */
		public static function get($keyOrElse, $mtimeOrElse, $callback, $params = null, $ttl = null);
		
		/**
		 * 删除指定键的缓存数据。
		 * @param $string $key
		 * @return void
		 */
		public static function delete($key);
	}
}