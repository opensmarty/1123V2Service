<?php
namespace App\Mvc {
	use App\System as Sys;

	/**
	 * 应用程序控制器基类。
	 */
	abstract class Controller extends \Phalcon\Mvc\Controller {
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
		 * 事件前缀。
		 */
		protected $eventPrefix = null;
		
		/**
		 * 析构函数。
		 */
		final public function __destruct() {
		}
		
		/**
		 * 初始化控制器，注意：当取消了动作的执行时，此方法不会被调用。
		 */
		public function initialize() {
			// 安装终止化方法。
			if (method_exists($this, 'finalize')) {
				register_shutdown_function(array(
					$this, 
					'finalize'
				));
			}
		}
		
		/**
		 * 调用动作之前触发的事件，它只会被触发一次。
		 * @param object $dispatcher
		 * @return void
		 */
		final public function beforeExecuteRoute($dispatcher) {
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
			
			// 设置视图变量，因其这些变量也要在取消视图中使用故放置在此。
			$this->view->setVar('moduleName', $this->moduleName);
			$globalConfig = Sys::getConfig()->global;
			$this->view->setVar('globalConfig', $globalConfig);
			$this->view->setVar('gc', $globalConfig);
			$this->view->setVar('moduleConfig', $this->config);
			$this->view->setVar('mc', $this->config);
			
			// 引发动作调用前事件。
			$this->eventPrefix = lcfirst($this->moduleName) . ucfirst($this->dispatcher->getControllerName());
			$fullEventName = $this->eventPrefix . ':before' . ucfirst($this->dispatcher->getActionName());
			if (Sys::hasEventListener($this->eventPrefix) || Sys::hasEventListener($fullEventName)) {
				$canceled = false;
				$ret = Sys::fire($fullEventName, $this, array(
					'extra' => array(
						'fullEventName' => $fullEventName
					)
				), $canceled);
				if ($canceled) {
					$this->canceled($ret, $this->dispatcher->getActionName());
					return false;
				}
			}
		}
		
		/**
		 * 调用动作之后触发的事件，它只会被触发一次。
		 * @param object $dispatcher
		 * @return void
		 */
		final public function afterExecuteRoute($dispatcher) {
			// 引发动作调用后事件。
			$fullEventName = $this->eventPrefix . ':after' . ucfirst($this->dispatcher->getActionName());
			if (Sys::hasEventListener($this->eventPrefix) || Sys::hasEventListener($fullEventName)) {
				Sys::fire($fullEventName, $this, array(
					'extra' => array(
						'fullEventName' => $fullEventName
					)
				));
			}
		}
		
		/**
		 * 当动作被取消执行时调用的方法。
		 * @param string $returnValue 由事件处理器返回的值。
		 * @param string $action 由事件处理器取消的动作。
		 * @return void
		 */
		protected function canceled($returnValue, $action) {
			$this->view->returnValue = $returnValue;
			$this->view->action = $action;
			$this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
			$this->view->pick('canceled');
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
			$oldErrorLevel = error_reporting();
			error_reporting(0);
			$ret = parent::__get($name);
			error_reporting($oldErrorLevel);
			if (empty($ret)) {
				$class = get_class($this);
				Sys::throwException("属性 $class::$name 未被定义");
			}
			return $ret;
		}
	}
}