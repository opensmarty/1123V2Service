<?php
namespace App {
	use App\System as Sys;

	/**
	 * 应用程序核心加载器类。
	 */
	class Loader extends \Phalcon\Loader {
		/**
		 * 自动加载类。
		 * @see \Phalcon\Loader::autoLoad()
		 */
		public function autoLoad($className) {
			$dispatcher = Sys::getDispatcher();
			if (!empty($dispatcher)) {
				// 转换 FirstsecondController 格式(它系由Phalcon框架内部自动转换而来)的类名称为 FirstSecondController 格式。
				$controllerName = ucfirst($dispatcher->getControllerName()); // 控制器名称默认为URL中书写的名称。
				$className = preg_replace("#{$controllerName}Controller\$#i", "{$controllerName}Controller", $className);
			}
			parent::autoLoad($className);
		}
	}
}