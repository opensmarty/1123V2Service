<?php
namespace App\KvCache\Servicers {
	use App\System as Sys;
	use App\Mvc\Servicer;
	use App\KvCache\Memcache;
	use App\KvCache\Redis;
	use App\Cache\APC;
	use Phalcon\Cache\Frontend\Igbinary;
	use Phalcon\Cache\BackendInterface;
	use Phalcon\Kernel;

	/**
	 * 基于一致性哈希算法的变体实现的键值缓存集群。
	 *
	 * 实现的功能为：在动态增加减少服务器数量或权重以后，使得原有缓存还保有较高的命中率。
	 */
	class KVCacheCluster extends Servicer implements BackendInterface, \ArrayAccess {
		/**
		 * 默认虚拟服务器数量。
		 */
		const DEFAULT_VIRTUAL_SERVERS_COUNT = 256;
		
		/**
		 * 虚拟服务器数量。
		 */
		private $virtualServersCount = null;
		
		/**
		 * 真实服务器集合。
		 */
		private $realServersList = null;
		
		/**
		 * 虚拟服务器到真实服务器的映射。
		 */
		private $virtualToRealServersMap = null;
		
		/**
		 * 摘要散列密码。
		 */
		private $hashSalt = '(^_^)';
		
		/**
		 * 缓存数据文件。
		 */
		private $dbFile = '/runtime/KVCacheCluster.db';
		
		/**
		 * 旧的缓存数据。
		 */
		private $oldData = null;
		
		/**
		 * 关联到后端缓存实例的前端适配器。
		 */
		private $frontend = null;
		
		/**
		 * 最后存储的缓存键。
		 */
		private $lastKey = null;
		
		/**
		 * 从配置中获取真实的服务器列表。
		 * @return array
		 */
		private function getRealServersList() {
			$realServersList = array();
			$hostPortToRealServerMap = array();
			foreach ($this->config->realServersList as $server) {
				if (empty($server->host) || $server->port <= 0) {
					continue;
				}
				$server->host = strtolower(trim($server->host));
				$server->port = intval($server->port);
				if ($server->weight < 10) {
					$server->weight = 10;
				}
				$hostPort = $server->host . ':' . $server->port;
				if (!isset($hostPortToRealServerMap[$hostPort])) {
					$k = count($realServersList);
					$realServersList[] = $server->toArray();
					$hostPortToRealServerMap[$hostPort] = $k;
				}
				else {
					$k = $hostPortToRealServerMap[$hostPort];
					$realServersList[$k]['weight'] += $server->weight;
				}
			}
			if (count($realServersList) <= 0) {
				Sys::throwException('配置数据中没有指定真实的服务器信息');
			}
			return $realServersList;
		}
		
		/**
		 * 创建虚拟服务器到真实服务器的映射信息。
		 * @return array
		 */
		private function createServersMap() {
			// 设置虚拟服务器数量。
			if ($this->config->virtualServersCount > 0) {
				$virtualServersCount = $this->config->virtualServersCount;
			}
			else {
				$virtualServersCount = self::DEFAULT_VIRTUAL_SERVERS_COUNT;
			}
			
			// 设置真实服务器列表。
			$realServersList = $this->getRealServersList();
			
			// 设置单位权重到真实服务器编号的映射。
			$weightToRealServerMap = array();
			$k = 0;
			foreach ($realServersList as $server) {
				for ($i = 0; $i < $server['weight']; $i++) {
					$weightToRealServerMap[] = $k;
				}
				$k++;
			}
			$realServersWeight = count($weightToRealServerMap);
			
			// 设置虚拟服务器到真实服务器的映射。
			$virtualToRealServersMap = array_pad(array(), $virtualServersCount, 0);
			$maxVirtualServerNumber = $virtualServersCount - 1;
			for ($i = 0; $i <= $maxVirtualServerNumber; $i++) {
				$virtualToRealServersMap[$i] = $weightToRealServerMap[$i % $realServersWeight];
			}
			
			// 返回服务器映射信息。
			return array(
				'virtualServersCount' => $virtualServersCount, 
				'realServersList' => $realServersList, 
				'virtualToRealServersMap' => $virtualToRealServersMap
			);
		}
		
		/**
		 * 重建虚拟服务器到真实服务器的映射信息。
		 * @return array
		 */
		private function recreateServersMap() {
			// 恢复旧的参数设置。
			$virtualServersCount = $this->oldData['virtualServersCount'];
			$oldRealServersList = $this->oldData['realServersList'];
			$oldVirtualToRealServersMap = $this->oldData['virtualToRealServersMap'];
			
			// 设置旧的真实服务器到虚拟服务器的映射。
			$oldRealToVirtualServersMap = array();
			foreach ($oldVirtualToRealServersMap as $k => $v) {
				$server = $oldRealServersList[$v];
				$hostPort = $server['host'] . ':' . $server['port'];
				$oldRealToVirtualServersMap[$hostPort][] = $k;
			}
			
			// 设置新的真实服务器相关参数。
			$newRealServersList = $this->getRealServersList();
			$newHostPortToNumberMap = array(); //新的主机端口号到真实服务器编号的映射
			$newRealServersWeight = 0; //新的真实服务器的权重。
			$k = 0;
			foreach ($newRealServersList as $server) {
				$hostPort = $server['host'] . ':' . $server['port'];
				$newHostPortToNumberMap[$hostPort] = $k++;
				$newRealServersWeight += $server['weight'];
			}
			
			// 计算每一个新的真实服务器需要关联的虚拟服务器数量。
			$newRealToVirtualServersCountMap = array();
			$usedVirtualServersCount = 0; // 用过的虚拟服务器数量。
			$count = count($newRealServersList) - 1;
			for ($i = 0; $i < $count; $i++) {
				$server = $newRealServersList[$i];
				$hostPort = $server['host'] . ':' . $server['port'];
				$newCount = floor(($server['weight'] / $newRealServersWeight) * $virtualServersCount);
				$newRealToVirtualServersCountMap[$hostPort] = $newCount;
				$usedVirtualServersCount += $newCount;
			}
			$server = $newRealServersList[$i]; // 真实服务器列表中的最末尾服务器。
			$hostPort = $server['host'] . ':' . $server['port'];
			$newRealToVirtualServersCountMap[$hostPort] = $virtualServersCount - $usedVirtualServersCount;
			
			// 计算需要重新映射的虚拟服务器列表。
			$remapVirtualServersList = array();
			$newRealToVirtualServersMapEqual = array(); // Equal意为真实服务器已关联了足够的虚拟服务器。
			$newRealToVirtualServersMapLess = array(); // Less意为真实服务器关联的虚拟服务器还不够。
			foreach ($oldRealToVirtualServersMap as $hostPort => $list) {
				if (isset($newRealToVirtualServersCountMap[$hostPort])) {
					// 旧的真实服务器存在于新的真实服务器列表中。
					$oldCount = count($list); // 旧的虚拟服务器数量。
					$newCount = $newRealToVirtualServersCountMap[$hostPort];
					if ($oldCount == $newCount) {
						$newRealToVirtualServersMapEqual[$hostPort] = $list;
					}
					elseif ($oldCount < $newCount) {
						$newRealToVirtualServersMapLess[$hostPort] = $list;
					}
					else {
						// 截掉原来虚拟服务器列表尾部的不再由新的真实服务器来处理的那一部分虚拟服务器。
						$remapVirtualServersList = array_merge($remapVirtualServersList, array_slice($list, $newCount));
						$newRealToVirtualServersMapEqual[$hostPort] = array_slice($list, 0, $newCount);
					}
				}
				else {
					// 旧的真实服务器已不在新的真实服务器列表中，与此旧的真实服务器关联的所有虚拟服务器都需要重新映射。
					$remapVirtualServersList = array_merge($remapVirtualServersList, $list);
				}
			}
			if (empty($remapVirtualServersList)) {
				// 返回旧的服务器映射信息。
				return array(
					'virtualServersCount' => $virtualServersCount, 
					'realServersList' => $oldRealServersList, 
					'virtualToRealServersMap' => $oldVirtualToRealServersMap
				);
			}
			
			// 重新映射需要重新映射的虚拟服务器。
			foreach ($newRealToVirtualServersMapLess as $hostPort => $list) {
				$oldCount = count($list);
				$newCount = $newRealToVirtualServersCountMap[$hostPort];
				$count = $newCount - $oldCount;
				$newRealToVirtualServersMapEqual[$hostPort] = array_merge($list, array_slice($remapVirtualServersList, 0, $count));
				$remapVirtualServersList = array_slice($remapVirtualServersList, $count);
			}
			foreach ($newRealToVirtualServersCountMap as $hostPort => $newCount) {
				if (!isset($newRealToVirtualServersMapEqual[$hostPort])) {
					// 给新增加的真实服务器关联足够的虚拟服务器。
					$newRealToVirtualServersMapEqual[$hostPort] = array_slice($remapVirtualServersList, 0, $newCount);
					$remapVirtualServersList = array_slice($remapVirtualServersList, $newCount);
				}
			}
			
			// 生成新的虚拟服务器到真实服务器的映射。
			$newVirtualToRealServersMap = array();
			foreach ($newRealToVirtualServersMapEqual as $hostPort => $list) {
				$k = $newHostPortToNumberMap[$hostPort];
				foreach ($list as $i) {
					$newVirtualToRealServersMap[$i] = $k;
				}
			}
			ksort($newVirtualToRealServersMap);
			
			// 返回新的服务器映射信息。
			return array(
				'virtualServersCount' => $virtualServersCount, 
				'realServersList' => $newRealServersList, 
				'virtualToRealServersMap' => $newVirtualToRealServersMap
			);
		}
		
		/**
		 * 获取新数据的回调函数。
		 * @param integer $flag 缓存键不存在还是已到期的标志。
		 * @return array
		 */
		private function callback($flag) {
			// 校验缓存数据文件的存在及合法性。
			$writeDBFile = false;
			$this->dbFile = $this->moduleDir . $this->dbFile;
			if (!is_file($this->dbFile)) {
				$data = $this->createServersMap();
				$writeDBFile = true;
			}
			else {
				$data = file_get_contents($this->dbFile);
				$oldHashCode = substr($data, 0, 32);
				$seriData = substr($data, 32);
				$newHashCode = md5(md5($seriData) . $this->hashSalt);
				if ($newHashCode != $oldHashCode) {
					$data = $this->createServersMap();
					$writeDBFile = true;
				}
				else {
					// 如果缓存到期了就说明配置文件已被修改过，此时就需要重建虚拟服务器到真实服务器的映射。
					$data = igbinary_unserialize($seriData);
					if ($flag == APC::KEY_DATA_EXPIRES) {
						$this->oldData = $data;
						$data = $this->recreateServersMap();
						$writeDBFile = true;
					}
				}
			}
			
			// 写入缓存数据。
			if ($writeDBFile) {
				$dbFileDir = dirname($this->dbFile);
				if (!is_dir($dbFileDir)) {
					if (mkdir($dbFileDir, 0700, true) === false) {
						Sys::throwException("创建目录 {$dbFileDir} 时失败");
					}
				}
				$seriData = igbinary_serialize($data);
				$hashCode = md5(md5($seriData) . $this->hashSalt);
				if (file_put_contents($this->dbFile, $hashCode . $seriData, LOCK_EX) === false) {
					Sys::throwException("向文件 {$this->dbFile} 写入缓存数据时失败");
				}
			}
			
			// 返回新数据。
			return $data;
		}
		
		/**
		 * 服务初始化方法。
		 * @return void
		 */
		public function initialize() {
			// 从缓存数据恢复参数。
			$_this = $this;
			$data = APC::get('_KVCacheCluster', $this->config->_modifiedTime, function ($flag) use($_this) {
				return $_this->callback($flag);
			});
			$this->virtualServersCount = $data['virtualServersCount'];
			$this->realServersList = $data['realServersList'];
			$this->virtualToRealServersMap = $data['virtualToRealServersMap'];
			
			// 设置缓存前端适配器。
			$this->frontend = new Igbinary();
		}
		
		/**
		 * 根据键名称获取真实的后端缓存对象。
		 * @param string $keyName 键名称。
		 * @return \Phalcon\Cache\BackendInterface
		 */
		private function getBackend($keyName) {
			$keyName = trim($keyName);
			if (strlen($keyName) == 0) {
				Sys::throwException('没有指定有效的缓存键', 2);
			}
			$this->lastKey = $keyName;
			
			// 根据键名称计算出真实的服务器编号。
			$virtualNumber = fmod(Kernel::preComputeHashKey32($keyName), $this->virtualServersCount);
			$realNumber = $this->virtualToRealServersMap[$virtualNumber];
			
			// 获取缓存后端实例，如果最佳服务器连接不上将会选择后继邻近的服务器。
			$realServerCount = count($this->realServersList);
			for ($i = 0; $i < 2 && $i < $realServerCount; $i++) {
				$realServer = &$this->realServersList[($realNumber + $i) % $realServerCount];
				if (isset($realServer['backend'])) {
					break;
				}
				if (extension_loaded('memcache')) {
					$memcahe = memcache_connect($realServer['host'], $realServer['port'], 2);
					if ($memcahe !== false) {
						$realServer['backend'] = new Memcache($this->frontend, $memcahe);
						break;
					}
				}
				else {
					Sys::throwException('没有安装memcache缓存扩展', 2);
				}
			}
			if (!isset($realServer['backend'])) {
				Sys::throwException('无法连接到真实的后端缓存服务器', 2);
			}
			$backend = $realServer['backend'];
			return $backend;
		}
		
		/**
		 * 获取关联到后端缓存实例的前端适配器。
		 * @return \Phalcon\Cache\FrontendInterface
		 */
		public function getFrontend() {
			return $this->frontend;
		}
		
		/**
		 * 获取最后存储的缓存键。
		 * @return string
		 */
		public function getLastKey() {
			return $this->lastKey;
		}
		
		/**
		 * 获取指定缓存键的内容。
		 * @param int|string $keyName
		 * @param long $lifetime 已被废弃。
		 * @return mixed
		 */
		public function get($keyName, $lifetime = null) {
			return $this->getBackend($keyName)->get($keyName);
		}
		
		/**
		 * 存储数据到指定的缓存键。
		 * @param int|string $keyName 缓存键名。
		 * @param mixed $content 缓存内容。
		 * @param long $lifetime 生命期秒数，如果值能转换为整型0时则代表永远缓存(实际内部值为0x7FFFFFFF)。
		 * @param boolean $stopBuffer 已被废弃。
		 * @return void
		 */
		public function save($keyName = null, $content = null, $lifetime = null, $stopBuffer = null) {
			$lifetime = intval('0' . $lifetime);
			if ($lifetime == 0) {
				$lifetime = 0x7FFFFFFF;
			}
			return $this->getBackend($keyName)->save($keyName, $content, $lifetime, false);
		}
		
		/**
		 * 删除指定的缓存键。
		 * @param int|string $keyName
		 * @return boolean
		 */
		public function delete($keyName) {
			return $this->getBackend($keyName)->delete($keyName);
		}
		
		/**
		 * 校验指定的缓存键是否存在并且也没有到期。
		 * @param string $keyName 缓存键名称。
		 * @param long $lifetime 已被废弃。
		 * @return boolean
		 */
		public function exists($keyName = null, $lifetime = null) {
			return $this->getBackend($keyName)->exists($keyName);
		}
		
		/**
		 * 判断指定索引的缓存数据是否存在。
		 * @param integer|string $offset 数据项索引。
		 * @return boolean
		 */
		public function offsetExists($offset) {
			return $this->getBackend($offset)->exists($offset);
		}
		
		/**
		 * 获取指定索引的缓存数据。
		 * @param integer|string $offset 数据项索引。
		 * @return mixed
		 */
		public function offsetGet($offset) {
			return $this->getBackend($offset)->get($offset);
		}
		
		/**
		 * 设置指定索引的缓存数据。
		 * @param integer|string $offset 数据项索引。
		 * @param value 数据项数据。
		 * @return void
		 */
		public function offsetSet($offset, $value) {
			$this->getBackend($offset)->save($offset, $value, 0, false);
		}
		
		/**
		 * 移除指定索引的缓存数据。
		 * @param integer|string $offset 数据项索引。
		 * @return void
		 */
		public function offsetUnset($offset) {
			$this->getBackend($offset)->delete($offset);
		}
		
		/*******************************************************************************/
		// 以下方法因为没有缓存键参数而无法应用于缓存集群或因前端不支持所以全部已被废弃。    
		/*******************************************************************************/
		
		/**
		 * 启动前端缓存(已被废弃)。
		 * @param int|string $keyName
		 * @param long $lifetime
		 * @return void
		 */
		public function start($keyName, $lifetime = null) {
		}
		
		/**
		 * 停止前端缓存(已被废弃)。
		 * @param boolean $stopBuffer
		 * @return void
		 */
		public function stop($stopBuffer = null) {
		}
		
		/**
		 * 查询存在的缓存键(已被废弃)。
		 * @param string $prefix
		 * @return array
		 */
		public function queryKeys($prefix = null) {
		}
		
		/**
		 * 校验是否已启动了输出缓存(已被废弃)。
		 * @return boolean
		 */
		public function isStarted() {
			return false;
		}
		
		/**
		 * 设置最后使用的缓存键(已被废弃)。
		 * @deprecated
		 * @param string $lastKey
		 * @return void
		 */
		public function setLastKey($lastKey) {
		}
		
		/**
		 * 获取后端选项信息(已被废弃)。
		 * @return array
		 */
		public function getOptions() {
		}
		
		/**
		 * 不知何意(已被废弃)。
		 * @return boolean
		 */
		public function isFresh() {
		}
		
		/**
		 * 清除掉所有已存在的数据(已被废弃)。
		 * @return boolean
		 */
		public function flush() {
		}
	}
}