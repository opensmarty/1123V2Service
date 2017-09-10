<?php
namespace App\Controllers {
	use App\System as Sys;

	/**
     * 默认控制器。
     */
	class IndexController extends ControllerBase {
		/**
		 * @see \App\Controllers\ControllerBase::initialize()
		 */
		public function initialize() {
			parent::initialize();
		}

		/**
         * 默认动作。
         */
		public function indexAction() {
			if (isset($_GET['_url'])) {
				$requestURI = $_GET['_url'];
				echo "请求的文件 $requestURI 不存在，请检查您请求的URI是否正确，然后再重试！";
			}
			else {
				header('Location:index.html');
			}
			exit();
		}

		/**
         * 未处理的异常处理动作。
         */
		public function exceptionAction() {
			// 暂把未处理的异常交还给系统去处理。
			throw Sys::getUnhandledException();
		}

		/**
         * 拉取Git仓库内容。
         */
		public function gitpullAction() {
			echo str_replace("\n", '<br/>', shell_exec('git pull'));
			exit('<br/>拉取完成！');
		}
	}
}
