<?php
namespace App\Mvc {
	use App\System as Sys;
	use App\FileSystem as FS;
	use App\Aspect;
	use App\Cache\APC;
	use Phalcon\Mvc\ModuleDefinitionInterface;
	use Phalcon\Annotations\Reader;
	use Phalcon\Config\Adapter\Ini;
	use App\Exception;

	/**
	 * 应用程序模块基本类。
	 */
	abstract class Module implements ModuleDefinitionInterface {
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
		 * 模块方面目录。
		 */
		protected $aspectDir = null;
		
		/**
		 * 方面信息列表。
		 */
		protected $aspectList = null;
		
		/**
		 * 首字母大写的模块名称。
		 */
		protected $moduleNameUC = null;
		
		/**
		 * 模块构造函数。
		 */
		final public function __construct() {
			// 初始化参数。
			$this->moduleDir = dirname((new \ReflectionObject($this))->getFileName());
			$this->moduleName = substr($this->moduleDir, strrpos($this->moduleDir, DIRECTORY_SEPARATOR) + 1);
			$this->di = Sys::getInstance()->getDI();
			$this->moduleNameUC = ucfirst($this->moduleName);
		}
		
		/**
		 * 析构函数。
		 */
		final public function __destruct() {
		}
		
		/**
		 * 设置模块配置信息。
		 * @param \Phalcon\Config $config
		 * @return void
		 */
		public function setConfig(\Phalcon\Config $config = null) {
			if (!empty($config)) {
				$this->config = $config;
			}
		}
		
		/**
		 * 获取模块名称。
		 * @return string
		 */
		public function getModuleName() {
			return $this->moduleName;
		}
		
		/**
		 * 为模块注册类自动加载路径。
		 * @param boolean $frontRequest 是否是前端请求了此模块，即要去执行此模块的某个动作，如是则会注册模块的控制器路径。
		 * @return void
		 */
		protected function registerAutoloadersFor($frontRequest = true) {
			// 获取模块基本目录。
			$moduleDir = $this->moduleDir;
			$moduleName = $this->moduleName;
			$moduleNameUC = $this->moduleNameUC;
			
			// 要注册的命名空间。
			$namespaces = array();
			if ($frontRequest) {
				// 注册模块控制器自动加载路径。
				$controllerDir = $moduleDir . DIRECTORY_SEPARATOR . 'controllers';
				if (FS::hasPHPFile($controllerDir)) {
					$namespaces["App\\$moduleNameUC\\Controllers"] = $controllerDir;
				}
			}
			else {
				// 注册模块库自动加载路径。
				$librayDir = $moduleDir . DIRECTORY_SEPARATOR . 'libraries';
				$librayDir2 = $librayDir . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . $moduleNameUC;
				$librayDirLen = strlen($librayDir);
				$dirs = FS::scandirEx($librayDir2)['dirs'];
				if (is_array($dirs)) {
					foreach ($dirs as $dir) {
						if (FS::hasPHPFile($dir)) {
							$namespace = str_replace(DIRECTORY_SEPARATOR, '\\', substr($dir, $librayDirLen + 1));
							$namespaces[$namespace] = $dir;
						}
					}
				}
				
				// 注册模块组件自动加载路径。
				$componentDir = $moduleDir . DIRECTORY_SEPARATOR . 'components';
				if (FS::hasPHPFile($componentDir)) {
					$namespaces["App\\$moduleNameUC\\Components"] = $componentDir;
				}
				
				// 注册模块服务者自动加载路径。
				$servicerDir = $moduleDir . DIRECTORY_SEPARATOR . 'servicers';
				if (FS::hasPHPFile($servicerDir)) {
					$namespaces["App\\$moduleNameUC\\Servicers"] = $servicerDir;
				}
				
				// 注册模块模型自动加载路径。
				$modelDir = $moduleDir . DIRECTORY_SEPARATOR . 'models';
				if (FS::hasPHPFile($modelDir)) {
					$namespaces["App\\$moduleNameUC\\Models"] = $modelDir;
				}
			}
			
			// 注册命名空间。
			$this->di->getShared('loader')->registerNamespaces($namespaces, true);
		}
		
		/**
		 * 为模块注册或设置服务参数。
		 * @param boolean $frontRequest 是否是前端请求了此模块，即要去执行此模块的某个动作，如是则会设置模块的视图基本目录。
		 * @return void
		 */
		protected function registerServicesFor($frontRequest = true) {
			// 设置模块目录名称。
			$moduleDir = $this->moduleDir;
			$moduleName = $this->moduleName;
			$di = $this->di;
			
			// 前端请求了此模块。
			if ($frontRequest && !Sys::isCliMode()) {
				$this->di->getShared('view')->setViewsDir($moduleDir . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR);
				return;
			}
			
			// 加载模块配置。
			$configFilePHP = $moduleDir . '/configs/config.php';
			$configFileINI = $moduleDir . '/configs/config.ini';
			$modifiedTimePHP = 0;
			$modifiedTimeINI = 0;
			if (is_file($configFilePHP)) {
				$modifiedTimePHP = filemtime($configFilePHP);
			}
			if (is_file($configFileINI)) {
				$modifiedTimeINI = filemtime($configFileINI);
			}
			$modifiedTime = max($modifiedTimePHP, $modifiedTimeINI);
			$configKey = '_' . $moduleName . 'ModuleConfig';
			$config = null;
			if ($modifiedTime > 0) {
				$config = APC::get($configKey, $modifiedTime, function () use($configFilePHP, $configFileINI, $modifiedTimePHP, $modifiedTimeINI, $modifiedTime) {
					// 刷新配置信息。
					$config = null;
					if ($modifiedTimePHP > 0) {
						include $configFilePHP;
					}
					if ($modifiedTimeINI > 0) {
						if (empty($config)) {
							$config = new Ini($configFileINI);
						}
						else {
							$config->merge(new Ini($configFileINI));
						}
					}
					if (!empty($config)) {
						$config['_modifiedTime'] = $modifiedTime;
					}
					return $config;
				});
				if (empty($config)) {
					APC::delete($configKey);
				}
			}
			Sys::getConfig()->$moduleName = $config;
			$this->config = $config;
			
			// 注册模块服务。
			$servicesFile = $moduleDir . '/configs/services.php';
			if (is_file($servicesFile)) {
				include $servicesFile;
			}
			
			// 加载模块路由配置。
			$routesFile = $moduleDir . '/configs/routes.php';
			if (is_file($routesFile)) {
				$router = $this->di->getShared('router');
				include $routesFile;
			}
		}
		
		/**
		 * 注册模块的类自动加载路径，仅在前端请求了此模块时被调用。
		 * @param \Phalcon\DiInterface $di
		 * @return void
		 */
		public function registerAutoloaders($di) {
			self::registerAutoloadersFor(true);
		}
		
		/**
		 * 注册模块提供的服务，仅在前端请求了此模块时被调用。
		 * @param \Phalcon\DiInterface $di
		 * @return void
		 */
		public function registerServices($di) {
			self::registerServicesFor(true);
			if (method_exists($this, 'onHttpRequest')) {
				$this->onHttpRequest();
			}
		}
		
		/**
		 * 解析方面文件。
		 * @param string $aspectFile 要解析的方面文件。
		 * @return array
		 */
		protected function parseAspectFile($aspectFile) {
			// 获取方面类名称。
			include_once $aspectFile;
			$aspectName = pathinfo($aspectFile, PATHINFO_BASENAME);
			$aspectName = substr($aspectName, 0, -4);
			$moduleNameUC = $this->moduleNameUC;
			$aspectClass = "App\\$moduleNameUC\\Aspects\\$aspectName";
			if (!class_exists($aspectClass, false) || !is_subclass_of($aspectClass, 'App\Mvc\Aspect')) {
				throw new Exception("方面类 $aspectClass 不存在或不是方面基类 App\\Mvc\\Aspect 的子类", "，在文件 $aspectFile 中");
			}
			
			// 解析方面文件中的方法文档块。
			$reader = new Reader();
			try {
				$parsedResult = $reader->parse($aspectClass);
			}
			catch (\Exception $e) {
				$line = strrchr($e->getMessage(), ' ');
				$lineLen = strlen($line) - 1;
				throw new Exception(substr($e->getMessage(), 0, -$lineLen) . ($line + 2), "，在文件 $aspectFile 中");
			}
			
			// 解析方面方法文档块中的@JoinPoint指令语句。
			$methods = array();
			$joinPointNamePattern = '#^[a-z]+(?::[a-z]+)?$#i';
			foreach ($parsedResult['methods'] as $aspectMethodName => $aspectMethodDirections) {
				// 遍历方面方法文档块中的所有@name指令语句。
				$joinPointNameList = array();
				foreach ($aspectMethodDirections as $aspectMethodDirection) {
					$aspectMethodDirectionName = $aspectMethodDirection['name'];
					if ($aspectMethodDirectionName != 'JoinPoint' && $aspectMethodDirectionName != 'PointCut') {
						continue;
					}
					$currentDirectionLine = $aspectMethodDirection['line'] + 2;
					if (!isset($aspectMethodDirection['arguments'])) {
						// @PointCut 后面无括号或括号里面没有内容的指令。
						throw new Exception("无效格式的指令语句 @{$aspectMethodDirectionName}，正确格式为：@{$aspectMethodDirectionName}(\"name|prefix:name\"|prefix={\"name\",...},...)，且括号中不能有数字、双引号也可有可无", "，在文件 $aspectFile 的第 $currentDirectionLine 行");
					}
					
					// 遍历@JoinPoint()或@PointCut()指令语句括号中的参数。
					foreach ($aspectMethodDirection['arguments'] as $argument) {
						if (isset($argument['name'])) {
							if (isset($argument['expr']['items'])) {
								foreach ($argument['expr']['items'] as $item) {
									if (isset($item['name'])) {
										// 如 demo={demo:beforeGetName} 这样的指令参数。
										throw new Exception("无效格式的指令语句 @{$aspectMethodDirectionName}，正确格式为：@{$aspectMethodDirectionName}(\"name|prefix:name\"|prefix={\"name\",...},...)，且括号中不能有数字、双引号也可有可无", "，在文件 $aspectFile 的第 $currentDirectionLine 行");
									}
									if (substr($argument['name'], -6) == 'Before') {
										$joinPointNameList[] = substr($argument['name'], 0, -6) . ':before' . ucfirst($item['expr']['value']);
									}
									elseif (substr($argument['name'], -5) == 'After') {
										$joinPointNameList[] = substr($argument['name'], 0, -5) . ':after' . ucfirst($item['expr']['value']);
									}
									else {
										$joinPointNameList[] = $argument['name'] . ':' . $item['expr']['value'];
									}
								}
							}
							else {
								$joinPointNameList[] = $argument['name'] . ':' . $argument['expr']['value'];
							}
						}
						else {
							$joinPointNameList[] = $argument['expr']['value'];
						}
					}
				}
				if (empty($joinPointNameList)) {
					continue;
				}
				
				// 校验联结点或切入点名称的合法性。
				$joinPointNameList = array_unique($joinPointNameList);
				foreach ($joinPointNameList as $joinPointName) {
					if (!preg_match($joinPointNamePattern, $joinPointName)) {
						throw new Exception("无效格式的指令语句 @{$aspectMethodDirectionName}，正确格式为：@{$aspectMethodDirectionName}(\"name|prefix:name\"|prefix={\"name\",...},...)，且括号中不能有数字、双引号也可有可无", "，在文件 $aspectFile 的第 $currentDirectionLine 行");
					}
				}
				
				// 存储方面方法解析结果。
				$methods[$aspectMethodName] = $joinPointNameList;
			}
			
			// 返回方面文件解析结果。
			$ret = array(
				'class' => $aspectClass, 
				'methods' => $methods
			);
			return $ret;
		}
		
		/**
		 * 织入方面代码到系统中去。
		 * @return void
		 */
		protected function weavinAspects() {
			// 遍历方面信息列表。
			foreach ($this->aspectList as $aspectFile => $aspectInfo) {
				// 创建方面类实例。
				include_once $aspectFile;
				$aspectObject = new $aspectInfo['class']();
				$reflection = new \ReflectionObject($aspectObject);
				
				// 遍历方面类方法。
				foreach ($aspectInfo['methods'] as $aspectMethodName => $joinPointNameList) {
					// 获取包装方面方法的闭包类对象。
					$aspectMethod = $reflection->getMethod($aspectMethodName)->getClosure($aspectObject);
					
					// 把联结点或切入点名称作为事件名称，方面方法作为事件处理器添加到系统中去。
					foreach ($joinPointNameList as $joinPointName) {
						if (strpos($joinPointName, ':') === false) {
							if (substr($joinPointName, -6) == 'Before') {
								$joinPointName = substr($joinPointName, 0, -6);
								Sys::addEventListener($joinPointName, function ($event) use($aspectMethod) {
									if (substr($event->getType(), 0, 6) == 'before') {
										return $aspectMethod($event);
									}
								});
								continue;
							}
							if (substr($joinPointName, -5) == 'After') {
								$joinPointName = substr($joinPointName, 0, -5);
								Sys::addEventListener($joinPointName, function ($event) use($aspectMethod) {
									if (substr($event->getType(), 0, 5) == 'after') {
										return $aspectMethod($event);
									}
								});
								continue;
							}
						}
						Sys::addEventListener($joinPointName, $aspectMethod);
					}
				} // 遍历方面类方法结束。
			} //遍历方面信息列表结束。
		}
		
		/**
		 * 运行模块。
		 * @return void
		 */
		public function run() {
			// 设置模块目录名称。
			$moduleDir = $this->moduleDir;
			$moduleName = $this->moduleName;
			
			// 进行模块内部初始化工作。
			$this->registerAutoloadersFor(false);
			$this->registerServicesFor(false);
			
			// 处理模块方面。
			$aspectKey = '_' . $this->moduleName . 'Aspects';
			$aspectDir = $this->aspectDir = $moduleDir . DIRECTORY_SEPARATOR . 'aspects';
			if (FS::hasPHPFile($aspectDir)) {
				$_this = $this;
				$aspectFile = null;
				$callback = function () use($_this, &$aspectFile) {
					return $_this->parseAspectFile($aspectFile);
				};
				$files = APC::get($aspectKey, filemtime($aspectDir), function () use($aspectDir) {
					$ret = array();
					$files = scandir($aspectDir);
					foreach ($files as $file) {
						$aspectFile = $aspectDir . DIRECTORY_SEPARATOR . $file;
						if (is_file($aspectFile) && substr($file, -4) == '.php') {
							$ret[] = $aspectFile;
						}
					}
					return $ret;
				});
				foreach ($files as $aspectFile) {
					$this->aspectList[$aspectFile] = APC::get('_' . $aspectFile, filemtime($aspectFile), $callback);
				}
				
				// 织入方面代码到系统中去。
				$this->weavinAspects();
			}
			else {
				APC::delete($aspectKey);
			}
			
			// 调用用户模块初始化方法。
			if (method_exists($this, 'initialize')) {
				$this->initialize();
				
				// 安装用户模块终止化方法。
				if (method_exists($this, 'finalize')) {
					register_shutdown_function(array(
						$this, 
						'finalize'
					));
				}
			}
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
