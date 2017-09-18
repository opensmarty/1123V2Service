<?php
namespace App\Mvc\Model {
	use App\System as Sys;

	/**
	 * 应用程序模型管理器类。
	 */
	class Manager extends \Phalcon\Mvc\Model\Manager {
		/**
		 * @see \Phalcon\Mvc\Model\Manager::initialize()
		 */
		public function initialize($model) {
			$model->initialize();
		}
	}
}
