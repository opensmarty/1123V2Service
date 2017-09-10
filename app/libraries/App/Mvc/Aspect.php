<?php
namespace App\Mvc {
	use App\System as Sys;

	/**
	 * 面向方面编程中的方面基类。
	 * 实现原理是：在方面的方法文档块中写上 @JoinPoint或@PointCut(user:getName,等) 这样格式的语句，系统就会自
	 * 动把该方法作为 user:getName 事件的事件处理器，随后就会在发生 user:getName 事件时调用到该方面方法，所以
	 * 说方面在此的本质就是一组事件处理器，因其方面方法是作为事件处理器来对待的，所以当方面方法返回非null值时
	 * 将会取消掉与此事件对应的默认操作，详情可参考App\System类。其中的 @JoinPoint与@PointCut 指的是面向方面
	 * 编程中的联结点与切入点，在此把引发事件的位置作为了方面程序的联结点或切入点。
	 * 
	 * 完整的指令语句格式为：
	 * 
	 * @JoinPoint或@PointCut("name[Before|After]|prefix:name"|prefix[Before|After]={"name",...},...) 且括号中不能有数字、双引号也可有可无。
	 * 
	 * name[Before|After] 意为：名称后面可跟 Before 或 After，它代表的是 name:before 或 name:after 系列的联结点，
	 * 
	 * prefix[Before|After] 意思同上代表 prefix:before 或 prefix:after 系列的联结点。
	 * 
	 * 举例如：
	 * 
	 * @PointCut(user:getName, "user:getAge", admin={login, "logout"}, demoBefore={"sayHello"})
	 * 
	 * 它所描述的切入点名称为：user:getName, user:getAge, admin:login, admin:logout, demo::beforeSayHello。
	 * 
	 * 注意：重复的名称会被去掉，还有整个过程只是一种面向方面编程的模拟，真正的面向方面的编程是需要语言支持的。
	 */
	abstract class Aspect {
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
		 * 方面构造函数，因其必须被调用到，所以用final关键字进行了限定，这样子类就不会有它自己的构造函数了。
		 */
		final public function __construct() {
			// 初始化参数。
			$this->moduleDir = dirname(dirname((new \ReflectionObject($this))->getFileName()));
			$this->moduleName = substr($this->moduleDir, strrpos($this->moduleDir, DIRECTORY_SEPARATOR) + 1);
			$this->config = Sys::getConfig()->get($this->moduleName);
			$this->di = Sys::getInstance()->getDI();
			
			// 初始化方面，此处的设计是为了与模型的initialize方法设计思想保持一致。
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