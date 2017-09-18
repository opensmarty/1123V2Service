<?php
namespace App {
	use \Phalcon\Db\Adapter\Pdo;
	use App\System as Sys;
	use App\Exception;
	use App\Mvc\RemoteServicerProxy;

	/**
	 * 应用程序依赖注入对象容器类。
	 */
	class DI extends \Phalcon\DI\FactoryDefault {
		/**
		 * 远程服务配置实例。
		 */
		protected $remoteServicesConfig = null;
		
		/**
		 * 是否是一个远程服务者代理请求。
		 */
		protected $isRSPRequest = false;
		
		/**
		 * 构造函数。
		 * @return void
		 */
		public function __construct() {
			parent::__construct();
			$globalConfig = Sys::getConfig()->global;
			if (isset($globalConfig->remoteServices)) {
				$this->remoteServicesConfig = $globalConfig->remoteServices;
			}
			$this->isRSPRequest = Sys::getInstance()->request->isRemoteServicerProxy();
		}
		
		/**
		 * 校验是否存在给定名称的服务。
		 * @param string $name 服务名称。
		 * @return boolean
		 */
		public function has($name) {
			if (isset($this->remoteServicesConfig->$name) && isset($this->remoteServicesConfig->$name->url) && isset($this->remoteServicesConfig->$name->key)) {
				$ret = true;
			}
			else {
				$ret = parent::has($name);
			}
			return $ret;
		}
		
		/**
		 * 获取服务对象。
		 * @param string $name 服务名称。
		 * @param array $parameters 服务参数。
		 * @return object
		 */
		public function get($name, $parameters = null) {
			if (!$this->isRSPRequest && isset($this->remoteServicesConfig->$name) && isset($this->remoteServicesConfig->$name->url) && isset($this->remoteServicesConfig->$name->key)) {
				$remoteService = $this->remoteServicesConfig->$name;
				if (!isset($remoteService['shared']) || $remoteService['shared']) {
					if (!isset($this->_sharedInstances[$name])) {
						$this->_sharedInstances[$name] = new RemoteServicerProxy($name, $remoteService->url, $remoteService->key);
					}
					$ret = $this->_sharedInstances[$name];
				}
				else {
					$ret = new RemoteServicerProxy($name, $remoteService->url, $remoteService->key);
				}
			}
			else {
				$useParentGet = true;
				if (Sys::isCliMode() && isset($this->_services[$name])) {
					$service = $this->_services[$name];
					if ($service->isShared()) {
						// 原来的共享服务实例存在于属性$_services中的代表着服务定义的Service对象的属性$_sharedInstances中，当需要获取共享服务实例的时候就会从这个属性中去获取，
						// 它是真正的全局共享服务实例，但如果想在某一个时间段换一个共享服务实例，就需要换一种方法了，在此我们把共享服务实例放在$this->_sharedInstances属性中，
						// 如果$this->_sharedInstances中有，则用它，如果没有，则创建一个新的服务实例来作为此时及以后的共享服务实例，这样就可以在需要时换上一批新的共享服务实例。
						if (!isset($this->_sharedInstances[$name])) {
							// 获取一个新的服务实例来共享。
							$service->setShared(false);
							$this->_sharedInstances[$name] = parent::get($name, $parameters);
							$service->setShared(true);
						}
						$ret = $this->_sharedInstances[$name];
						$useParentGet = false;
					}
				}
				if ($useParentGet) {
					try {
						$ret = parent::get($name, $parameters);
					}
					catch (\Exception $e) {
						if ($e->getFile() == __FILE__) {
							// 说明是try块中的扩展库函数而不是它调用的PHP函数抛出了异常。
							Sys::throwException($e->getMessage());
						}
						else {
							throw $e;
						}
					}
				}
			}
			return $ret;
		}
		
		/**
		 * 获取共享服务对象。
		 * @param string $name 服务名称。
		 * @param array $parameters 服务参数。
		 * @return object
		 */
		public function getShared($name, $parameters = null) {
			if (!$this->isRSPRequest && isset($this->remoteServicesConfig->$name) && isset($this->remoteServicesConfig->$name->url) && isset($this->remoteServicesConfig->$name->key)) {
				$remoteService = $this->remoteServicesConfig->$name;
				if (!isset($this->_sharedInstances[$name])) {
					$this->_sharedInstances[$name] = new RemoteServicerProxy($name, $remoteService->url, $remoteService->key);
				}
				$ret = $this->_sharedInstances[$name];
			}
			else {
				// 在底层它会调用parent::get()方法并把返回结果存放在$_sharedInstances属性中，以便下次直接重用。
				try {
					$ret = parent::getShared($name, $parameters);
				}
				catch (\Exception $e) {
					if ($e->getFile() == __FILE__) {
						// 说明是try块中的扩展库函数而不是它调用的PHP函数抛出了异常。
						Sys::throwException($e->getMessage());
					}
					else {
						throw $e;
					}
				}
			}
			return $ret;
		}
		
		/**
		 * 清除掉共享的PDO服务实例(仅用于命令行定时任务中，用以清除掉可能会长时间不用的共享的PDO数据库连接，以免因连接空闲时间过长而导致超时，下次再使用时发现连接已断开，
		 * 从而因错误而导致定时任务终止，提示：在MySQL中空闲连接超时时间可由WAIT_TIMEOUT参数控制，注意：仅工作于命令行模式)。
		 * @return void
		 */
		public function cleanSharedPDOInstance() {
			if (Sys::isCliMode() && !empty($this->_sharedInstances)) {
				$sharedInstances = null;
				foreach ($this->_sharedInstances as $key => $val) {
					if ($val instanceof Pdo) {
						$val->close();
					}
					else {
						$sharedInstances[$key] = $val;
					}
				}
				$this->_sharedInstances = $sharedInstances;
			}
		}
	}
}