<?php
namespace App\Mvc {
	use App\System as Sys;

	/**
	 * 应用程序组件基类，组件就是控制器、服务者底层使用的且可重用的业务或服务逻辑单元，注意：它位于模型之上，所以模型中是不能使用它的。
	 * 它与类库中的类的区别是：类库中的类是与任何应用或业务都没有关系的代码单元，而这里的组件却是与应用或业务有关的代码单元。当然有些
	 * 服务者尽管与应用或业务并没有直接关系，像会话服务、键值缓存集群服务等，但是它们却是构建此类应用或业务环境重要的组成部分，所以它
	 * 们底层所使用的代码单元一般也要作为组件，当然视情况也可以放在类库中。
	 */
	abstract class Component {
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
		 * 组件构造函数，因其必须被调用到，所以用final关键字进行了限定，这样子类就不会有它自己的构造函数了。
		 */
		final public function __construct() {
			// 初始化参数。
			$this->moduleDir = dirname(dirname((new \ReflectionObject($this))->getFileName()));
			if ($this->moduleDir != APP_ROOT) {
				$this->moduleName = substr($this->moduleDir, strrpos($this->moduleDir, DIRECTORY_SEPARATOR) + 1);
				$this->config = Sys::getConfig()->get($this->moduleName);
			}
			else {
				$this->config = Sys::getConfig()->global;
			}
			$this->di = Sys::getInstance()->getDI();
			
			// 初始化组件，此处的设计是为了与模型的initialize方法设计思想保持一致。
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