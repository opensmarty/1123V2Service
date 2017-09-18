<?php
namespace App\Mvc {
	use App\System as Sys;

	/**
	 * 应用程序服务者基类。
	 */
	abstract class Servicer {
		/**
		 * 模块目录。
		 */
		protected $moduleDir = null;
		
		/**
		 * 模块名称。
		 */
		protected $moduleName = null;
		
		/**
		 * 模块配置。
		 */
		protected $config = null;
		
		/**
		 * 依赖注入容器。
		 */
		protected $di = null;
		
		/**
		 * 服务者构造函数，因其必须被调用到，所以用final关键字进行了限定，这样子类就不会有它自己的构造函数了。
		 * @param array $options 选项信息，这个只是为了与Phalcon框架中的某些接口保持兼容，但实际并未使用到它。
		 */
		final public function __construct($options = null) {
			// 设置模块信息。
			$this->moduleDir = dirname(dirname((new \ReflectionObject($this))->getFileName()));
			if ($this->moduleDir != APP_ROOT) {
				$this->moduleName = substr($this->moduleDir, strrpos($this->moduleDir, DIRECTORY_SEPARATOR) + 1);
				$this->config = Sys::getConfig()->get($this->moduleName);
			}
			else {
				$this->config = Sys::getConfig()->global;
			}
			$this->di = Sys::getInstance()->getDI();
			
			// 初始化服务者，此处的设计是为了与模型的initialize方法设计思想保持一致。
			if (method_exists($this, 'initialize')) {
				$this->initialize();
				
				// 安装终止化方法。
				if (method_exists($this, 'finalize')) {
					register_shutdown_function(array(
						$this, 
						'finalize'
					));
				}
			}
		}
		
		/**
		 * 析构函数。
		 */
		final public function __destruct() {
		}
		
		/**
		 * 当调用了不存在的方法时调用。
		 * @param string $name 方法名称。
		 * @param array $arguments 方法参数。
		 * @return mixed
		 */
		public function __call($name, array $arguments) {
			$class = get_class($this);
			Sys::throwException("方法 $class::$name() 未被定义");
		}
		
		/**
		 * 当获取不存在的属性时调用。
		 * @param string $name
		 * @return mixed
		 */
		public function __get($name) {
			if (!$this->di->has($name)) {
				$class = get_class($this);
				Sys::throwException("属性 $class::$name 未被定义");
			}
			return $this->di->get($name);
		}
	}
}