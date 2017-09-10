<?php
namespace App\Mvc {
	use App\System as Sys;
	use App\Exception;

	/**
	 * 应用程序视图基类。
	 */
	class View extends \Phalcon\Mvc\View {
		/**
		 * 默认主题名称。
		 */
		const DEFAULT_THEME = 'default';
		
		/**
		 * 视图主题名称。
		 */
		protected $theme = self::DEFAULT_THEME;
		
		/**
		 * 旧的视图目录。
		 */
		protected $oldViewsDir = null;
		
		/**
		 * 构造函数。
		 * @param array $options
		 */
		final public function __construct(array $options = null) {
			parent::__construct($options);
			$this->request = Sys::getInstance()->request;
			$this->setVar('theme', $this->theme);
			$this->setVar('di', Sys::getInstance()->getDI());
		}
		
		/**
		 * 析构函数。
		 */
		final public function __destruct() {
		}
		
		/**
		 * 设置视图主题。
		 * @param string $theme 视图主题名称。
		 * @return void
		 */
		public function setViewsTheme($theme) {
			$theme = trim($theme);
			if (strlen($theme) == 0) {
				return;
			}
			$this->theme = $theme;
			$this->setVar('theme', $this->theme);
			if (is_dir($this->oldViewsDir)) {
				$this->_viewsDir = $this->oldViewsDir . DIRECTORY_SEPARATOR . $this->theme . DIRECTORY_SEPARATOR;
			}
		}
		
		/**
		 * 获取视图主题。
		 * @return string 视图主题。
		 */
		public function getViewsTheme() {
			return $this->theme;
		}
		
		/**
		 * 设置视图目录。
		 * @param string $dir 视图基本目录。
		 * @return void
		 */
		public function setViewsDir($dir) {
			if (!is_dir($dir)) {
				Sys::throwException("无效的视图目录[$dir]");
			}
			$this->oldViewsDir = rtrim($dir, '/\\');
			$this->_viewsDir = $this->oldViewsDir . DIRECTORY_SEPARATOR . $this->theme . DIRECTORY_SEPARATOR;
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
	}
}