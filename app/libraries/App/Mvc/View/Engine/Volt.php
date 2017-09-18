<?php
namespace App\Mvc\View\Engine {
	use App\System as Sys;
	use App\Mvc\View\Engine\Volt\Compiler;

	/**
	 * 应用程序Volt模板引擎。
	 */
	class Volt extends \Phalcon\Mvc\View\Engine\Volt {
		/**
		 * 获取模板编译器。
		 * @see \Phalcon\Mvc\View\Engine\Volt::getCompiler()
		 */
		public function getCompiler() {
			if (empty($this->_compiler)) {
				$this->_compiler = new Compiler($this->_view);
				$this->_compiler->setDI($this->_dependencyInjector);
				$this->_compiler->setOptions($this->_options);
			}
			return $this->_compiler;
		}
	}
}
