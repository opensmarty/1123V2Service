<?php
namespace App\Session {
	use App\Mvc\Module as ModuleBase;

	/**
	 * 模块类。
	 */
	class Module extends ModuleBase {
		/**
		 * 运行时事件处理器。
		 * @return void
		 */
		public function onRun() {
			if (isset($this->config->autoStart) && $this->config->autoStart) {
				$this->di->get('session')->start();
			}
		}
	}
}