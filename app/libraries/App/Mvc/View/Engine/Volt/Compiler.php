<?php
namespace App\Mvc\View\Engine\Volt {
	use App\System as Sys;
	use App\Utils\PHtmlCompressor;

	/**
	 * 应用程序Volt模板引擎编译器。
	 */
	class Compiler extends \Phalcon\Mvc\View\Engine\Volt\Compiler {
		/**
		 * 编译一个模板文件并写入到一个指定的文件。
		 * @see \Phalcon\Mvc\View\Engine\Volt\Compiler::compileFile()
		 */
		public function compileFile($path, $compiledPath, $extendsMode = null) {
			$rs = parent::compileFile($path, $compiledPath, false);
			file_put_contents($compiledPath, PHtmlCompressor::compress($rs), LOCK_EX);
		}
	}
}
