<?php
namespace App\Session\Servicers {
	use App\System as Sys;
	use App\Mvc\Servicer;
	use App\Session\AdapterInterface;
	use Phalcon\Config;
	use App\Exception;

	/**
	 * 基于键值数据库的会话服务类。
	 * 
	 * 在会话中保存数据的键名格式建议为：moduleName(模块名称) + DataKeyName(数据键名称)。
	 */
	class KVDBSession extends Servicer implements AdapterInterface {
		/**
		 * 会话底层使用的默认Cookie名称。
		 */
		const DEFAULT_COOKIE_NAME = 'KVDBSESSIONID';
		
		/**
		 * 会话默认生命期秒数。
		 */
		const DEFAULT_LIFETIME = 1800;
		
		/**
		 * 会话数据。
		 */
		private $data = null;
		
		/**
		 * Cookie名称。
		 */
		private $cookieName = self::DEFAULT_COOKIE_NAME;
		
		/**
		 * 会话生命期。
		 */
		private $lifeTime = self::DEFAULT_LIFETIME;
		
		/**
		 * 会话标识符。
		 */
		private $id = null;
		
		/**
		 * 会话是否已经启动。
		 */
		private $isStarted = false;
		
		/**
		 * 初始化方法。
		 * @return void
		 */
		public function initialize() {
		}
		
		/**
		 * 终止化方法。
		 * @return void
		 */
		public function finalize() {
			if (!Sys::isCliMode()) {
				if (!$this->isStarted || empty($this->data)) {
					return;
				}
				
				$this->kvcache->save($this->id, $this->data, $this->lifeTime);
			}
		}
		
		/**
		 * 启动会话。
		 * @param array $options 选项信息。
		 * @return void
		 */
		public function start(array $options = null) {
			if (!Sys::isCliMode()) {
				if ($this->isStarted) {
					return;
				}
				
				// 合并选项信息。
				if (is_array($options)) {
					$this->config->merge(new Config($options));
				}
				
				// 设置Cookie名称。
				if (isset($this->config->cookieName)) {
					$cookieName = trim($this->config->cookieName);
					if (strlen($cookieName) > 0) {
						$this->cookieName = $cookieName;
					}
				}
				
				// 设置生命期。
				if (isset($this->config->lifeTime)) {
					$lifeTime = intval('0' . $this->config->lifeTime);
					if ($lifeTime > 0) {
						$this->lifeTime = $lifeTime;
					}
					else {
						$this->lifeTime = self::DEFAULT_LIFETIME;
					}
				}
				
				// 设置会话ID。
				$kvcache = $this->kvcache;
				$cookieNameLength = 26;
				if (isset($_COOKIE[$this->cookieName]) && strlen($_COOKIE[$this->cookieName]) == $cookieNameLength) {
					$this->id = $_COOKIE[$this->cookieName];
				}
				else {
					while (true) {
						$this->id = substr(md5(microtime() . uniqid()), 0, $cookieNameLength);
						if (!$kvcache->exists($this->id)) {
							break;
						}
					}
				}
				
				// 发送携带会话ID的Cookie。 
				if (headers_sent($file, $line)) {
					Sys::throwException("HTTP头部已经在文件 $file 的第 $line 行发出，从而导致不能发送携带会话ID的Cookie，因此会话启动失败");
				}
				setcookie($this->cookieName, $this->id, 0, '/', null, false, true);
				
				// 获取原来的会话信息。
				if ($kvcache->exists($this->id)) {
					$this->data = $kvcache->get($this->id);
				}
			}
			
			// 设置会话已启动标志。
			$this->isStarted = true;
		}
		
		/**
		 * 获取索引指定的会话信息，返回引用型数据提供了通过多维数组的形式操作会话数据的功能，如：$sessionData['key']['item']。
		 * @param string $index 索引。
		 * @param mixed $defaultValue 默认值。
		 * @return mixed
		 */
		public function &get($index, $defaultValue = null) {
			if (!isset($this->data[$index])) {
				$this->data[$index] = $defaultValue;
			}
			return $this->data[$index];
		}
		
		/**
		 * 设置索引指定的会话信息。
		 * @param string $index 索引。
		 * @param mixed $value 数据。
		 * @return void
		 */
		public function set($index, $value) {
			if (!$this->isStarted) {
				Sys::throwException('会话还未被启动，不能在会话中保存数据');
			}
			if ($this->config->autoStart && is_object($value)) {
				Sys::throwException('在自动启动会话的情况下不能在会话中保存类实例，因为在以后恢复类实例时它的所属类没有机会被加载，从而会导致错误');
			}
			$this->data[$index] = $value;
		}
		
		/**
		 * 判断是否有索引指定的会话信息。
		 * @param string $index 索引。
		 * @return boolean
		 */
		public function has($index) {
			return isset($this->data[$index]);
		}
		
		/**
		 * 移除索引指定的会话信息。
		 * @param string $index 索引。
		 * @return void
		 */
		public function remove($index) {
			unset($this->data[$index]);
		}
		
		/**
		 * 获取当前的会话ID。
		 * @return string
		 */
		public function getId() {
			return $this->id;
		}
		
		/**
		 * 校验会话是否已经启动。
		 * @return boolean
		 */
		public function isStarted() {
			return $this->isStarted;
		}
		
		/**
		 * 销毁当前会话。
		 * @return boolean
		 */
		public function destroy($sessionId = null) {
			if ($this->isStarted) {
				if (!headers_sent()) {
					setcookie($this->cookieName, null, 0, '/');
				}
				$this->kvcache->delete($this->id);
				$this->data = null;
				$this->id = null;
				$this->isStarted = false;
			}
			return true;
		}
		
		/**
		 * 判断指定索引的会话数据是否存在。
		 * @param integer|string $offset 数据项索引。
		 * @return boolean
		 */
		public function offsetExists($offset) {
			return isset($this->data[$offset]);
		}
		
		/**
		 * 获取指定索引的会话数据，返回引用型数据提供了通过多维数组的形式操作会话数据的功能，如：$session['key']['item']。
		 * @param integer|string $offset 数据项索引。
		 * @return mixed
		 */
		public function &offsetGet($offset) {
			if (!isset($this->data[$offset])) {
				$this->data[$offset] = null;
			}
			return $this->data[$offset];
		}
		
		/**
		 * 设置指定索引的会话数据。
		 * @param integer|string $offset 数据项索引。
		 * @param value 数据项数据。
		 * @return void
		 */
		public function offsetSet($offset, $value) {
			if (!$this->isStarted) {
				Sys::throwException('会话还未被启动，不能在会话中保存数据');
			}
			if ($this->config->autoStart && is_object($value)) {
				Sys::throwException('在自动启动会话的情况下不能在会话中保存类实例，因为在以后恢复类实例时它的所属类没有机会被加载，从而会导致错误');
			}
			$this->data[$offset] = $value;
		}
		
		/**
		 * 移除指定索引的会话数据。
		 * @param integer|string $offset 数据项索引。
		 * @return void
		 */
		public function offsetUnset($offset) {
			unset($this->data[$offset]);
		}
		
		/**
		 * 设置选项信息(已被废弃)。
		 * @param $options 选项信息。
		 * @return void
		 */
		public function setOptions(array $options) {
		}
		
		/**
		 * 获取选项信息(已被废弃)。
		 * @return array
		 */
		public function getOptions() {
		}

		public function regenerateId($deleteOldSession = NULL) {

        }
        public function setName($name) {

        }

        public function getName() {

        }
	}
}
