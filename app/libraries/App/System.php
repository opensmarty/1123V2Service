<?php
namespace App {
	use Phalcon\Config\Adapter\Ini;
	use Phalcon\Config;
	use Phalcon\Loader;
	use App\FileSystem as FS;
	use App\Events\Manager;
	use App\Cache\APCU;
	use App\Exception;
	use App\DI;
	use App\Http\Request;

	/**
	 * 代表整个应用程序的类。
	 */
	class System extends \Phalcon\Mvc\Application {
		/**
		 * 默认动态配置信息刷新间隔时间，单位秒数。
		 */
		const DEFAULT_DYNAMIC_CONFIG_REFRESH_INTERVAL = 86400;
		
		/**
		 * 动态配置信息在键值缓存中的缓存键。
		 */
		protected static $dynamicConfigKey = '_dynamicConfig';
		
		/**
		 * 系统配置对象。
		 */
		protected static $config = null;
		
		/**
		 * 系统类单实例。
		 */
		protected static $instance = null;
		
		/**
		 * 临界区锁集合。
		 */
		protected static $flocks = null;
		
		/**
		 * 临界区锁集合。
		 */
		protected static $locks = null;
		
		/**
		 * 系统事件管理器，在通过call方法间接调用服务方法时用来派发事件，这样就可以通过事件监听器来动态的在服务方法的前
		 * 后挂接代码，以改变程序的原有执行流程，此方式避免了不同功能代码的相互交织情况，从而可以使得同样的功能代码集中
		 * 编写在一起，如：日志功能放在一起、权限检查可放在一起等，简单的说就是在模拟面向方面的编程。
		 */
		protected static $eventsManager = null;
		
		/**
		 * 先前的异常实例。
		 */
		protected static $previousException = null;
		
		/**
		 * 未经处理的异常。
		 */
		protected static $unhandledException = null;
		
		/**
		 * 系统派发器实例。
		 */
		protected static $dispatcher = null;
		
		/**
		 * 是否已执行过关闭处理器函数(当为true时不可再抛出异常)。
		 */
		protected static $doShutdown = false;
		
		/**
		 * 是否位于异常处理器执行期间。
		 */
		protected static $inExceptionHandler = false;
		
		/**
		 * 是否运行在命令行模式。
		 */
		protected static $isCliMode = false;
		
		/**
		 * 是否输出GBK编码结果。
		 */
		protected static $outputGBKEncoding = false;
		
		/**
		 * 原生的$_GET变量值。
		 */
		protected static $rawGet = null;
		
		/**
		 * 原生的$_POST变量值。
		 */
		protected static $rawPost = null;
		
		/**
		 * 原生的$_REQUEST变量值。
		 */
		protected static $rawRequest = null;
		
		/**
		 * 系统是否已完全启动。
		 */
		protected static $isLaunched = false;
		
		/**
		 * 系统构造函数。
		 */
		public function __construct() {
			// 构造应用程序实例。
			if (!empty(self::$instance)) {
				return;
			}
			parent::__construct();
			if (self::$isCliMode) {
				// 关闭自动缓存。
				$this->useImplicitView(false);
			}
			self::$instance = $this;
			
			// 保存原生的变量值。
			self::$rawGet = $_GET;
			self::$rawPost = $_POST;
			self::$rawRequest = $_REQUEST;
			
			// 注册关闭函数。
			register_shutdown_function(function () {
				self::$doShutdown = true;
			});
			
			// 初始化临时用类自动加载器。
			$loader = new Loader();
			$loader->registerDirs(array(
				dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'libraries'
			));
			$loader->register();
			
			// 预定义系统常量。
			self::defineConstants();
			
			// 设置错误处理器。
			self::setErrorHandler();
			
			// 加载应用程序配置信息。
			self::loadConfigs();
			
			// 初始化类自动加载器。
			self::initializeLoader();
			
			// 终止化临时用类自动加载器同时启用正式用类自动加载器。
			$loader->unregister();
			$this->di->getShared('loader')->register();
			
			// 设置事件管理器。
			self::$eventsManager = new Manager();
			self::$eventsManager->enablePriorities(true);
			
			// 设置系统时区。
			$defaultTimeZone = 'Asia/Shanghai';
			if (!empty(self::$config->global->timezone)) {
				if (!date_default_timezone_set(self::$config->global->timezone)) {
					date_default_timezone_set($defaultTimeZone);
				}
			}
			else {
				date_default_timezone_set($defaultTimeZone);
			}
			
			// 转换输入数据。
			self::translateInputData($_GET);
			self::translateInputData($_POST);
			self::translateInputData($_REQUEST);
			
			// 扫描模块目录下的所有可用模块并安装它。
			$this->scanAndSetupModules();
		}
		
		/**
		 * 加载应用程序配置信息。
		 * @return void
		 */
		protected static function loadConfigs() {
			// 加载配置信息。
			$configFilePHP = APP_ROOT . '/configs/config.php';
			$configFileINI = APP_ROOT . '/configs/config.ini';
			$modifiedTimePHP = 0;
			$modifiedTimeINI = 0;
			if (is_file($configFilePHP)) {
				$modifiedTimePHP = filemtime($configFilePHP);
			}
			if (is_file($configFileINI)) {
				$modifiedTimeINI = filemtime($configFileINI);
			}
			$modifiedTime = max($modifiedTimePHP, $modifiedTimeINI);
			$config = APCU::get('_globalConfig', $modifiedTime, function () use($configFilePHP, $configFileINI, $modifiedTimePHP, $modifiedTimeINI, $modifiedTime) {
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
				else {
					$config = new Config(array());
				}
				return $config;
			});
			self::$config = new Config(array());
			self::$config->global = $config;
			
			// 加载服务配置。
			self::$instance->request = new Request();
			$di = new DI();
			$di->set('request', self::$instance->request, true);
			$diFile = APP_ROOT . '/configs/services.php';
			if (is_file($diFile)) {
				include $diFile;
			}
			self::$instance->setDI($di);
			
			// 加载路由配置。
			$routesFile = APP_ROOT . '/configs/routes.php';
			if (is_file($routesFile)) {
				$router = $di->getShared('router');
				include $routesFile;
			}
		}
		
		/**
		 * 保存应用程序动态配置信息。
		 * @param array $configs 格式为：[配置标识][模块名称][名称1]...[名称n] = 配置值。
		 * @return void
		 */
		public static function saveDynamicConfigs(array $configs) {
			$kvcache = self::$instance->getDI()->getShared('kvcache');
			foreach ($configs as $key => $val) {
				$dynamicConfigKey = self::$dynamicConfigKey . ucfirst($key);
				$kvcache->save($dynamicConfigKey, $val);
			}
		}
		
		/**
		 * 加载应用程序动态配置信息。
		 * @return void
		 */
		protected static function loadDynamicConfigs() {
			// 加载动态配置信息。
			if (isset(self::$config->global->dynamicConfigIdentifier)) {
				$dynamicConfigKey = self::$dynamicConfigKey . ucfirst(self::$config->global->dynamicConfigIdentifier);
			}
			else {
				$dynamicConfigKey = self::$dynamicConfigKey;
			}
			if (isset(self::$config->global->dynamicConfigRefreshInterval)) {
				$refreshInterval = intval('0' . self::$config->global->dynamicConfigRefreshInterval);
			}
			else {
				$refreshInterval = self::DEFAULT_DYNAMIC_CONFIG_REFRESH_INTERVAL;
			}
			$dynamicConfig = APCU::get($dynamicConfigKey, -$refreshInterval, function () use($dynamicConfigKey) {
				// 刷新动态配置信息。
				$config = null;
				$kvcache = self::$instance->getDI()->getShared('kvcache');
				if ($kvcache->exists($dynamicConfigKey)) {
					$config = $kvcache->get($dynamicConfigKey);
				}
				return $config;
			});
			if (empty($dynamicConfig)) {
				return;
			}
			
			// 合并动态配置信息。
			foreach ($dynamicConfig as $key => $val) {
				if (isset(self::$config->$key)) {
					self::$config->$key->merge(new Config($val));
				}
				else {
					self::$config->$key = new Config($val);
				}
			}
		}
		
		/**
		 * 初始化类自动加载器。
		 * @return void
		 */
		protected static function initializeLoader() {
			// 设置类自动加载路径。
			$loader = self::$instance->di->getShared('loader');
			$loader->registerDirs(array(
				APP_ROOT . DIRECTORY_SEPARATOR . 'libraries'
			));
			
			// 要注册的命名空间。
			$namespaces = array();
			
			// 注册控制器自动加载路径。
			$controllerDir = APP_ROOT . DIRECTORY_SEPARATOR . 'controllers';
			if (FS::hasPHPFile($controllerDir)) {
				$namespaces['App\Controllers'] = $controllerDir;
			}
			
			// 注册组件自动加载路径。
			$componentDir = APP_ROOT . DIRECTORY_SEPARATOR . 'components';
			if (FS::hasPHPFile($componentDir)) {
				$namespaces['App\Components'] = $componentDir;
			}
			
			// 注册服务者自动加载路径。
			$servicerDir = APP_ROOT . DIRECTORY_SEPARATOR . 'servicers';
			if (FS::hasPHPFile($servicerDir)) {
				$namespaces['App\Servicers'] = $servicerDir;
			}
			
			// 注册模型自动加载路径。
			$modelDir = APP_ROOT . DIRECTORY_SEPARATOR . 'models';
			if (FS::hasPHPFile($modelDir)) {
				$namespaces['App\Models'] = $modelDir;
			}
			
			// 注册命名空间。
			$loader->registerNamespaces($namespaces);
			
			// 设置视图基本目录。
			$view = self::getInstance()->di->getShared('view');
			$view->setViewsDir(APP_ROOT . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR);
		}
		
		/**
		 * 设置错误处理器。
		 * @return void
		 */
		protected static function setErrorHandler() {
			// 设置错误处理器。
			set_error_handler(function ($errno, $errstr, $errfile, $errline, array $errcontext = null) {
				if (preg_match('#^Use of undefined constant aop_((?i)[a-z]+)\x20#', $errstr, $match)) {
					// 处理 Use of undefined constant aop_名称 这样的错误。
					$fullEventName = 'aop:' . $match[1];
					if (self::$eventsManager->hasListeners($fullEventName)) {
						self::$eventsManager->collectResponses(false);
						self::$eventsManager->fire($fullEventName, null, array(
							'extra' => array(
								'fullEventName' => $fullEventName, 
								'joinPointFile' => $errfile, 
								'joinPointLine' => $errline
							)
						), false);
					}
				}
				else {
					// 处理其它错误(提示：错误处理器中的错误会被强行显示)。
					$errorLevel = error_reporting();
					if (!($errno & $errorLevel)) {
						return true;
					}
					$stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
					$calls = '';
					$count = count($stack);
					for ($i = 1; $i < $count; $i++) {
						if (isset($stack[$i]['class'])) {
							$calls = $stack[$i]['class'] . '::' . $stack[$i]['function'] . '() ' . $calls;
						}
						else {
							$calls = $stack[$i]['function'] . '() ' . $calls;
						}
						if (isset($stack[$i]['file'])) {
							$function = strtolower($stack[$i]['function']);
							if ($function != '__call' && $function != '__callstatic') {
								if (isset($stack[0]['file'])) {
									// 说明是在上层用户函数的内部发生了错误，如未定义的变量错误。
									$errorSource = '，在文件 ' . $stack[$i]['file'] . ' 的第 ' . $stack[$i]['line'] . ' 行的 ' . $calls . '方法中的第 ' . $errline . ' 行发生了错误，错误源文件为 ' . $errfile;
								}
								else {
									// 说明是在上层系统函数的内部报告了错误，如不正确的传参错误。
									$errorSource = '，在文件 ' . $stack[$i]['file'] . ' 的第 ' . $stack[$i]['line'] . ' 行的 ' . $calls . '方法处发生了错误，错误源文件为 ' . $errfile;
								}
								$exception = new Exception($errstr, $errorSource, null, self::$previousException);
								self::$previousException = $exception;
								if (!self::$doShutdown || self::$inExceptionHandler) {
									// 当没有执行到关闭函数或者位于异常处理器执行期间时就抛出异常，因为此时异常一定会被捕获到。
									throw $exception;
								}
								else {
									self::exceptionHandler($exception);
								}
							}
						}
						$calls = '=> ' . $calls;
					}
					if (isset($stack[0]['file'])) {
						// 说明当前函数调用栈序为：扩展库函数=>用户函数=>错误处理器，见于关闭函数、缓存输出回调及析构函数的最后阶段中发生了未定义的变量错误时。
						$errorSource = '，在文件 ' . $stack[0]['file'] . ' 的第 ' . $stack[0]['line'] . ' 行发生了错误';
					}
					else {
						// 说明当前函数调用栈序为：扩展库函数=>错误处理器，由于是扩展库代码调用了错误处理器，所以没有文件及行号，见于ob_gzhandler等函数执行时。
						$errorSource = null;
					}
					$exception = new Exception($errstr, $errorSource, null, self::$previousException);
					self::$previousException = $exception;
					self::exceptionHandler($exception);
				}
			});
			
			// 设置异常处理器。
			set_exception_handler(function (\Exception $e) {
				self::exceptionHandler($e);
			});
		}
		
		/**
		 * 异常处理器(会自动执行exit语句)。
		 * @param App\Exception $e
		 * @return void
		 */
		protected static function exceptionHandler(\Exception $e) {
			self::$inExceptionHandler = true;
			try {
				if (self::$instance->request->isAjax()) {
					echo $e->getMessage();
				}
				elseif (self::$instance->request->isRemoteServicerProxy()) {
					$message = igbinary_serialize(array(
						$e->getMessage()
					));
					$message = $message . md5($message);
					echo $message;
				}
				elseif (self::$isLaunched) {
					// 因此时有可能位于缓存输出回调函数中，所以不能开启缓存，故执行useImplicitView(false)与partial('index/exception')手动渲染动作模板。
					self::$unhandledException = $e;
					$app = self::getInstance();
					$app->useImplicitView(false);
					$response = $app->handle('/index/exception');
					$app->view->setViewsDir(APP_ROOT . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR);
					$app->view->partial('index/exception');
				}
				else {
					throw $e;
				}
			}
			catch (\Exception $e) {
				if ($e instanceof Exception) {
					$message = $e->getFullMessage();
				}
				else {
					$message = $e->getMessage() . '，在文件 ' . $e->getFile() . ' 的第 ' . $e->getLine() . ' 行';
				}
				if (self::$isCliMode) {
					$message = str_replace('<br/>', "\n", $message);
					$message = rtrim($message, '。！') . '。';
					if (self::$outputGBKEncoding) {
						// 因有些时候输出不会被缓存回调函数处理，如典型的 echo 数组变量，不知道为什么，故在此关闭缓存，以防止对某些输出的重复处理，并转换本次输出为GBK编码。
						ob_end_flush();
						$message = mb_convert_encoding($message, 'GBK');
					}
					elseif (DIRECTORY_SEPARATOR == '/') {
						// 在linux样系统上进行输出换行。
						$message .= "\n";
					}
					echo $message;
				}
				else {
					$message = str_replace(array(
						APP_ROOT, 
						"\n"
					), array(
						'APP_ROOT', 
						'<br/>'
					), htmlspecialchars($message));
					$message = '描述：' . rtrim($message, '。！') . '。';
					echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
					echo '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head><body>';
					echo '<div style="width:98%;height:auto;line-height:30px;font-size:13px;font-weight:bold;margin-left:auto;margin-right:auto;padding:10px;padding-top:3px;padding-bottom:3px;border:1px solid #BBBBBB;background:#CCCCFF;text-align:left;white-space:pre-wrap;word-break:break-all;overflow:hidden;">' . $message . '</div>';
					echo '</body></html>';
				}
			}
			exit();
		}
		
		/**
		 * 预定义系统常量。
		 * @return void
		 */
		protected static function defineConstants() {
			define('PRIVATE_ROOT', dirname(dirname(__DIR__)));
			define('PUBLIC_ROOT', str_replace('/', DIRECTORY_SEPARATOR, $_SERVER['DOCUMENT_ROOT']));
			define('APP_ROOT', PRIVATE_ROOT);
            define('VENDOR_ROOT', dirname(dirname(dirname(__DIR__) )). DIRECTORY_SEPARATOR . 'vender');
		}
		
		/**
		 * 扫描模块目录下的所有可用模块并安装它。
		 * @return void
		 */
		protected function scanAndSetupModules() {
			// 设置模块目录。
			$modulesDir = APP_ROOT . DIRECTORY_SEPARATOR . 'modules';
			
			// 遍历目录下的所有模块并注册它。
			$modules = array();
			$dirs = scandir($modulesDir);
			foreach ($dirs as $dir) {
				if ($dir != '.' && $dir != '..') {
					$moduleFile = $modulesDir . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . 'Module.php';
					if (file_exists($moduleFile)) {
						// 注册模块。
						$className = 'App\\' . ucfirst($dir) . '\Module';
						$modules[$dir] = array(
							'className' => $className, 
							'path' => $moduleFile
						);
					}
				}
			}
			$this->registerModules($modules);
			
			// 让所有模块运行起来。
			$moduleInstances = array();
			foreach ($modules as $module) {
				include $module['path'];
				$moduleInstance = new $module['className']();
				$moduleInstances[] = $moduleInstance;
				$moduleInstance->run();
			}
			
			// 加载应用程序动态配置信息。
			self::loadDynamicConfigs();
			
			// 触发用户模块事件。
			foreach ($moduleInstances as $moduleInstance) {
				// 设置模块配置信息。
				$moduleName = $moduleInstance->getModuleName();
				$moduleInstance->setConfig(self::$config->$moduleName);
				
				// 触发用户模块的首次运行事件，它仅会被触发一次，所以在此事件处理程序中模块可以作一些特殊的初始化工作。
				APCU::get('_' . $moduleName . 'Run', 1, function () use($moduleInstance) {
					if (method_exists($moduleInstance, 'onFirstRun')) {
						$moduleInstance->onFirstRun();
					}
					return true;
				});
				
				// 触发用户模块的每次运行事件。
				if (method_exists($moduleInstance, 'onRun')) {
					$moduleInstance->onRun();
				}
			}
		}
		
		/**
		 * 获取系统类单实例。
		 * @return App\System
		 */
		public static function getInstance() {
			if (empty(self::$instance)) {
				self::$instance = new self();
			}
			return self::$instance;
		}
		
		/**
		 * 获取系统配置对象。
		 * @return \Phalcon\Config
		 */
		public static function getConfig() {
			return self::$config;
		}
		
		/**
		 * 转换输入数据(以防止XSS攻击)。
		 * @param &array $data
		 * @return void
		 */
		public static function translateInputData(array &$data) {
			foreach ($data as &$val) {
				if (is_array($val)) {
					self::translateInputData($val, false);
				}
				else {
					$val = htmlspecialchars($val, ENT_COMPAT | ENT_HTML401, mb_internal_encoding(), false);
				}
			}
		}
		
		/**
		 * 运行应用程序。
		 * @param boolean $outputGBKEncoding 是否输出GBK编码结果，默认false，即原样输出。
		 * @return void
		 */
		public static function run($outputGBKEncoding = false) {
			// 设置运行模式。
			mb_internal_encoding('UTF-8');
			if (php_sapi_name() == 'cli') {
				set_time_limit(0);
				self::$isCliMode = true;
				
				// 关闭默认开启的缓冲区，以达到实时输出的效果。
				if (ob_get_level() > 0) {
					ob_end_clean();
				}
				
				// 转换命令行的输出编码，之后创建应用程序实例。
				self::$outputGBKEncoding = ($outputGBKEncoding == true);
				if (self::$outputGBKEncoding) {
					// 1是为了达到实时输出的效果。
					ob_start(function ($data) {
						return mb_convert_encoding($data, 'GBK');
					}, 1);
				}
			}
			else {
				ignore_user_abort(true);
				header('Content-Type:text/html; charset=' . mb_internal_encoding());
				header('Cache-Control:no-cache no-store');
			}
			
			// 运行应用程序。
			$app = self::getInstance();
			self::$dispatcher = $app->di->getShared('dispatcher'); // 此步必须放在这，因为下一步就会设置控制器信息，这样后续代码在获取控制器信息时就能够获取到。
			self::$isLaunched = true;
            #根据debugbar.php存放的路径，适当的调整引入的相对路径
            $app->di['app'] = $app;
            (new \Snowair\Debugbar\ServiceProvider())->start();
			$response = $app->handle();
			exit($response->getContent());
		}
		
		/**
		 * 获取系统私有根目录。
		 * @return string
		 */
		public static function getPrivateRoot() {
			return PRIVATE_ROOT;
		}
		
		/**
		 * 获取系统公有根目录。
		 * @return string
		 */
		public static function getPublicRoot() {
			return PUBLIC_ROOT;
		}
		
		/**
		 * 抛出异常函数，此函数会报告出引发错误的真正文件及行号，如当执行 A->B->throwException 时，将会报告A函数调用B函数的文件及行号。
		 * @param string $message 异常消息。
		 * @param string|integer $functionNameOrOffset 函数调用栈中的某个函数名或该函数在函数调用栈中的偏移量，用于报告出该函数被调用的文件及行号信息，默认为调用throwException方法的函数偏移量，其值为1。
		 * @return void
		 */
		public static function throwException($message, $functionNameOrOffset = null) {
			$stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			$count = count($stack);
			$offset = 1;
			if (isset($functionNameOrOffset)) {
				if (is_numeric($functionNameOrOffset)) {
					$offset = $functionNameOrOffset;
					if ($offset < 1 || $offset >= $count) {
						$message = '无效的函数偏移量 ' . $functionNameOrOffset;
						$errorSource = '，在文件 ' . $stack[0]['file'] . ' 的第 ' . $stack[0]['line'] . ' 行';
						$exception = new Exception($message, $errorSource, null, self::$previousException);
						self::$previousException = $exception;
						if (!self::$doShutdown) {
							throw $exception;
						}
						else {
							self::exceptionHandler($exception);
						}
					}
				}
				elseif (is_string($functionNameOrOffset) && !empty($functionNameOrOffset)) {
					for ($i = 1; $i < $count; $i++) {
						if ($stack[$i]['function'] == $functionNameOrOffset) {
							$offset = $i;
							break;
						}
					}
					if ($i >= $count) {
						$message = '函数调用栈中找不到函数 ' . $functionNameOrOffset;
						$errorSource = '，在文件 ' . $stack[0]['file'] . ' 的第 ' . $stack[0]['line'] . ' 行';
						$exception = new Exception($message, $errorSource, null, self::$previousException);
						self::$previousException = $exception;
						if (!self::$doShutdown) {
							throw $exception;
						}
						else {
							self::exceptionHandler($exception);
						}
					}
				}
			}
			$calls = '';
			for ($i = 1; $i < $count; $i++) {
				if (isset($stack[$i]['class'])) {
					$calls = $stack[$i]['class'] . '::' . $stack[$i]['function'] . '() ' . $calls;
				}
				else {
					$calls = $stack[$i]['function'] . '() ' . $calls;
				}
				if ($i >= $offset && isset($stack[$i]['file'])) {
					$function = strtolower($stack[$i]['function']);
					if ($function != '__call' && $function != '__callstatic') {
						$errorSource = '，在文件 ' . $stack[$i]['file'] . ' 的第 ' . $stack[$i]['line'] . ' 行的 ' . $calls . '方法中的第 ' . $stack[0]['line'] . ' 行抛出了异常，异常源文件为 ' . $stack[0]['file'];
						$exception = new Exception($message, $errorSource, null, self::$previousException);
						self::$previousException = $exception;
						if (!self::$doShutdown) {
							throw $exception;
						}
						else {
							self::exceptionHandler($exception);
						}
					}
				}
				$calls = '=> ' . $calls;
			}
		}
		
		/**
		 * 间接调用DI中注册的服务对象的方法，它会在实际调用服务对象方法的前后派发事件，如调用 user_getUserInfo 方法时，
		 * 将会引发 user:beforeGetUserInfo 与 user:afterGetUserInfo 事件，但是当服务不存在时将会抛出异常。
		 * @param string $name 方法名称，方法名称格式为：服务名称_服务方法名称，如：user_getUserInfo，请不要包含多余的_，否则会引发错误。
		 * @param array $arguments 方法参数。
		 * @return mixed
		 */
		public static function __callStatic($name, array $arguments) {
			// 校验并设置调用服务参数。
			static $pattern = '#^[a-z]+_[a-z]+$#i';
			if (!preg_match($pattern, $name)) {
				self::throwException('服务调用 ' . $name . ' 格式无效，正确格式为：服务名称_方法名称，且名称只能为字母组合');
			}
			list($service, $method) = explode('_', $name);
			
			// 进行服务调用。
			$source = null;
			if (self::$instance->di->has($service)) {
				$source = self::$instance->di->get($service);
			}
			if (!is_object($source) || !method_exists($source, $method)) {
				self::throwException('调用了不存在的服务 ' . $name);
			}
			
			// 引发方法调用前事件。
			$oldArguments = $arguments;
			$methodUC = ucfirst($method);
			$fullEventName = "$service:before$methodUC";
			$responses = null;
			if (self::$eventsManager->hasListeners($service) || self::$eventsManager->hasListeners($fullEventName)) {
				$arguments['extra'] = array(
					'fullEventName' => $fullEventName
				);
				self::$eventsManager->collectResponses(true);
				self::$eventsManager->fire($fullEventName, $source, $arguments, false);
				$responses = array_filter(self::$eventsManager->getResponses(), function ($item) {
					return $item !== null;
				});
			}
			
			// 调用实际服务方法，前提是没有任何一个事件监听器返回值。
			if (empty($responses)) {
				$ret = call_user_func_array(array(
					$source, 
					$method
				), $oldArguments);
				
				// 引发方法调用后事件。
				$fullEventName = "$service:after$methodUC";
				if (self::$eventsManager->hasListeners($service) || self::$eventsManager->hasListeners($fullEventName)) {
					$arguments['extra'] = array(
						'fullEventName' => $fullEventName
					);
					self::$eventsManager->collectResponses(false);
					self::$eventsManager->fire($fullEventName, $source, $arguments, false);
				}
			}
			else {
				// 用第一个事件处理器的返回结果代替服务调用的结果。
				$ret = current($responses);
				if (is_array($ret) && isset($ret[0]) && $ret[0] === false && array_key_exists(1, $ret)) {
					$ret = $ret[1];
				}
			}
			return $ret;
		}
		
		/**
		 * 添加事件监听器。
		 * @param string $type 要添加的事件类型，用法同Phalcon框架中事件管理器中的事件类型。
		 * @param object $listener 要添加的事件监听器，当同事件类型的任何一个事件监听器有返回值时，将不会执行事件对应的默认操作，
		 * 并会用第一个事件处理器的返回结果代替默认操作的返回结果，可用array(false, null)返回严格的null值代替默认操作的返回结果。
		 * @param integer $priority 添加的事件监听器被触发的优先级，数字越大优先级越高，但是注意：事件 name 的优先级永远低于 name:subname。
		 * @return void
		 */
		public static function addEventListener($type, $listener, $priority = 0) {
			return self::$eventsManager->attach($type, $listener, $priority);
		}
		
		/**
		 * 移除事件监听器。
	 	 * @param string $type 要移除的事件类型，当$type参数为null时，将移除所有的事件监听器。
	     * @param object $listener 要移除的事件监听器，当$listener参数为null时，将移除与$type关联的所有事件监听器。
		 * @return void
		 */
		public static function removeEventListener($type = null, $listener = null) {
			self::$eventsManager->detach($type, $listener);
		}
		
		/**
		 * 校验是否有给定事件类型的事件监听器存在。
		 * @param string $type 事件类型。
		 * @return boolean
		 */
		public static function hasEventListener($type) {
			return self::$eventsManager->hasListeners($type);
		}
		
		/**
		 * 引发一个事件。
		 * @param string $type 事件类型。
		 * @param object $source 事件来源。
		 * @param mixed $data 附加数据。
		 * @param boolean $canceled 这个参数是一参两用：
		 * 一：可通过传入true或false来决定在一个事件处理器中是否可通过调用$event->stop()方法来停止与此事件关联的后续事件处理器的执行；
		 * 二：可通过传出true或false来得知是否有一个事件处理器要取消掉与此事件关联的默认操作，默认操作就是此事件派发完后要执行的操作。
		 * @return return mixed 第一个取消默认操作的事件处理器的返回结果。
		 */
		public static function fire($type, $source, $data = null, &$canceled = false) {
			$ret = null;
			try {
				list($prefix) = explode(':', $type);
				if (self::$eventsManager->hasListeners($prefix) || self::$eventsManager->hasListeners($type)) {
					self::$eventsManager->collectResponses(true);
					self::$eventsManager->fire($type, $source, $data, $canceled);
					$responses = array_filter(self::$eventsManager->getResponses(), function ($item) {
						return $item !== null;
					});
					if (!empty($responses)) {
						$canceled = true;
						$ret = current($responses);
						if (is_array($ret) && isset($ret[0]) && $ret[0] === false && array_key_exists(1, $ret)) {
							$ret = $ret[1];
						}
					}
					else {
						$canceled = false;
					}
				}
				else {
					$canceled = false;
				}
			}
			catch (\Exception $e) {
				if (!($e instanceof Exception)) {
					self::throwException($e->getMessage());
				}
				else {
					throw $e;
				}
			}
			return $ret;
		}
		
		/**
		 * 对临界代码区进行加锁(通过flock函数实现，所以它是一个操作系统级的锁机制，也因此它可以工作在FastCGI的多进程环境下，但是它的效率不是很高)。
		 * @param string $name 锁名称。
		 * @return void
		 */
		public static function flock($name) {
			$name = trim($name);
			if (strlen($name) == 0) {
				self::throwException('锁名称参数不能为空');
			}
			if (isset(self::$flocks[$name])) {
				return;
			}
			static $lockDir = null;
			if (empty($lockDir)) {
				$lockDir = APP_ROOT . '/runtime/locks';
				if (!is_dir($lockDir) && (mkdir($lockDir, 0700, true) === false)) {
					self::throwException("创建锁目录 $lockDir 时失败");
				}
			}
			$lockFile = $lockDir . "/$name.lck";
			$lockHandle = fopen($lockFile, 'c');
			if ($lockHandle === false || !flock($lockHandle, LOCK_EX)) {
				self::throwException("加锁失败，可能是由于权限限制导致了创建加锁文件 $lockFile 时失败、或您的文件系统是不能加文件锁的FAT文件系统");
			}
			self::$flocks[$name] = array(
				$lockHandle, 
				$lockFile
			);
		}
		
		/**
		 * 对最近的临界代码区进行解锁。
		 * @param boolean $deleteLockFile 是否删除锁文件，对于频繁使用的锁建议不要设置为true，因为再次创建锁文件的开销比较大，默认为false。
		 * @return void
		 */
		public static function unflock($deleteLockFile = false) {
			if (!empty(self::$flocks)) {
				list($lockHandle, $lockFile) = array_pop(self::$flocks);
				flock($lockHandle, LOCK_UN);
				fclose($lockHandle);
				if ($deleteLockFile) {
					unlink($lockFile);
				}
			}
		}
		
		/**
		 * 对临界代码区进行加锁(基于APCU扩展与flock函数实现，但它是一个进程级的锁机制，也因此它只能工作在单进程的多线程环境下，但是它的效率比较高)。
		 * @param string $name 锁名称。
		 * @return void
		 */
		public static function lock($name) {
			$name = trim($name);
			if (strlen($name) == 0) {
				self::throwException('锁名称参数不能为空');
			}
			$oldName = $name;
			$name = "_lock$name";
			if (isset(self::$locks[$name])) {
				return;
			}
			
			// 初始化锁对应的缓存键。
			if (!apcu_exists($name)) {
				self::flock($oldName);
				if (!apcu_exists($name)) {
					// 只有第一个取得锁的线程才能初始化锁对应的缓存键。
					apcu_store($name, 0);
				}
				self::unflock(true);
			}
			
			// 获得指定的锁。
			while (!apcu_cas($name, 0, 1)) {
				usleep(mt_rand(10, 1000));
			}
			self::$locks[$name] = $name;
			
			// 安装解锁函数，以防止程序意外的终止而导致某些锁不能正常的解锁，从而进一步影响到后继程序的加锁请求永远得不到满足。
			static $setup = false;
			if (!$setup) {
				$setup = true;
				register_shutdown_function(function () {
					foreach (self::$locks as $name) {
						apcu_store($name, 0);
					}
				});
			}
		}
		
		/**
		 * 对最近的临界代码区进行解锁。
		 * @return void
		 */
		public static function unlock() {
			if (!empty(self::$locks)) {
				apcu_store(array_pop(self::$locks), 0);
			}
		}
		
		/**
		 * 获取系统派发器实例。
		 * @return App\Mvc\Dispatcher
		 */
		public static function getDispatcher() {
			return self::$dispatcher;
		}
		
		/**
		 * 获取未经处理的异常。
		 * @return \Exception
		 */
		public static function getUnhandledException() {
			return self::$unhandledException;
		}
		
		/**
		 * 获取是否运行在命令行模式。
		 * @return boolean
		 */
		public static function isCliMode() {
			return self::$isCliMode;
		}
		
		/**
		 * 让系统睡眠一定的秒数(注意：仅工作于命令行模式)。
		 * @param integer $seconds
		 * @return void
		 */
		public static function sleep($seconds) {
			if (self::$isCliMode) {
				self::$instance->getDI()->cleanSharedPDOInstance();
				gc_collect_cycles();
				sleep($seconds);
			}
		}
		
		/**
		 * 获取原生的$_GET变量值。
		 * @return array
		 */
		public static function getRawGet() {
			return self::$rawGet;
		}
		
		/**
		 * 获取原生的$_POST变量值。
		 * @return array
		 */
		public static function getRawPost() {
			return self::$rawPost;
		}
		
		/**
		 * 获取原生的$_REQUEST变量值。
		 * @return array
		 */
		public static function getRawRequest() {
			return self::$rawRequest;
		}
	}
}
