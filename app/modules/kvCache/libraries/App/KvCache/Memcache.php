<?php
namespace App\KvCache {
	use App\System as Sys;

	/**
	 * 后端缓存类。
	 */
	class Memcache extends \Phalcon\Cache\Backend\Memcache {
		/**
		 * 构造函数。
		 * @param Phalcon\Cache\FrontendInterface $frontend
		 * @param \Memcache $memcache
		 */
		public function __construct($frontend, $memcache) {
			if ($memcache instanceof \Memcache) {
				$this->_memcache = $memcache;
			}
			else {
				Sys::throwException('无效的 memcache 参数');
			}
			parent::__construct($frontend);
		}
	}
}
