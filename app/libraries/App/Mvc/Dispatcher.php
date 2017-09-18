<?php
namespace App\Mvc {
	use App\System as Sys;
	use App\Exception;
	use App\PHPX;

	/**
	 * 应用程序派发器。
	 */
	class Dispatcher extends \Phalcon\Mvc\Dispatcher {
		/**
		 * 进行真正的派发工作。
		 * @return \Phalcon\Mvc\ControllerInterface 最后的控制器实例。
		 */
		public function dispatch() {
			if (Sys::getInstance()->request->isRemoteServicerProxy()) {
				$rawInput = file_get_contents('php://input');
				$realData = substr($rawInput, 0, -32); // 后32个字节为MD5签名串。
				$oldErrorLevel = error_reporting();
				error_reporting(0);
				$request = igbinary_unserialize($realData);
				error_reporting($oldErrorLevel);
				$signKey = null;
				if ($request === null || empty($request['name']) || empty($request['url']) || empty($request['method'])) {
					$ret = '无效的服务调用请求';
				}
				else {
					$di = Sys::getInstance()->getDI();
					$config = Sys::getConfig()->global;
					$name = $request['name'];
					if (isset($config['listenServices'][$name]['name']) && !empty($config['listenServices'][$name]['name'])) {
						// 真实的服务名称。
						$realName = $config['listenServices'][$name]['name'];
					}
					else {
						$realName = $name;
					}
					if (!isset($config['listenServices'][$name]['url']) || !isset($config['listenServices'][$name]['key']) || $config['listenServices'][$name]['url'] != $request['url'] || !$di->has($realName)) {
						$ret = '请求了不存在的服务';
					}
					elseif (md5($realData . $config['listenServices'][$name]['key']) != substr($rawInput, -32)) {
						$ret = '用于安全的签名无效';
					}
					else {
						$service = $di->get($realName);
						if (!method_exists($service, $request['method'])) {
							$ret = '请求调用了不存在的服务方法';
						}
						else {
							// 引发服务调用时事件。
							$canceled = false;
							$fullEventName = $realName . ':on' . ucfirst($request['method']);
							if (Sys::hasEventListener($realName) || Sys::hasEventListener($fullEventName)) {
								$ret = Sys::fire($fullEventName, $service, array(
									'extra' => array(
										'fullEventName' => $fullEventName
									)
								), $canceled);
							}
							
							// 进行服务的实际调用。
							if (!$canceled) {
								$ret = call_user_func_array(array(
									$service, 
									$request['method']
								), $request['arguments']);
								$signKey = $config['listenServices'][$name]['key'];
							}
							else {
								$ret = PHPX::strval($ret);
							}
						}
					}
				}
				$ret = igbinary_serialize(array(
					$ret
				));
				$ret = $ret . md5($ret . $signKey);
				exit($ret);
			}
			else {
				// 修改控制器命名空间。
				$moduleName = $this->getModuleName();
				if (!empty($moduleName)) {
					$moduleName = ucfirst($moduleName) . "\\";
				}
				$this->setNamespaceName("App\\{$moduleName}Controllers");
				
				// 修改控制器名称。
				$this->_handlerName = lcfirst($this->_handlerName);
				
				// 修改动作名称。
				$this->_actionName = lcfirst($this->_actionName);
				
				// 转换类的名称。
				try {
					$ret = parent::dispatch();
				}
				catch (\Exception $e) {
					if ($e->getFile() == __FILE__) {
						// 说明是try块中的扩展库函数而不是它调用的PHP函数抛出了异常。
						// 转换 FirstsecondController 格式的类名称为 原始控制器名称首字母大写 格式。
						$handlerName = ucfirst($this->_handlerName);
						$message = preg_replace("#{$handlerName}Controller#i", "{$handlerName}Controller", $e->getMessage());
						Sys::throwException($message);
					}
					else {
						throw $e;
					}
				}
				return $ret;
			}
		}
	}
}
