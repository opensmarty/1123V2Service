<?php
namespace App\KvCache {
	use App\System as Sys;

	/**
	 * 后端缓存类。
	 */
	class Redis extends \Phalcon\Cache\Backend\Redis {
		/**
		 * 构造函数。
		 * @param Phalcon\Cache\FrontendInterface $frontend
		 * @param \Redis $redis
		 */
		public function __construct($frontend, $redis) {
			if ($redis instanceof \Redis) {
				$options['redis'] = $redis;
			}
			else {
				Sys::throwException('无效的 redis 参数');
			}
			parent::__construct($frontend, $options);
		}
	}
}
