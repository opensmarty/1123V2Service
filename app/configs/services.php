<?php
/*
 ***************************************************************************
 * 以下的设置一般不要更改。
 ***************************************************************************
 */

// 类自动加载服务。
$di->set('loader', function () use($config) {
	$loader = new \App\Loader();
	return $loader;
});

// 路由服务。
$di->set('router', function () use($di) {
	$router = new Phalcon\Mvc\Router();
	// $router->setUriSource(Phalcon\Mvc\Router::URI_SOURCE_SERVER_REQUEST_URI);
	$router->removeExtraSlashes(true);
	return $router;
});

// 派发器服务。
$di->set('dispatcher', function () {
	$dispatcher = new App\Mvc\Dispatcher();
	return $dispatcher;
});

// URL路径生成服务。
$di->set('url', function () use($config) {
	$url = new Phalcon\Mvc\Url();
	$url->setBaseUri($config->application->baseUri);
	return $url;
});

// 视图渲染服务。
$di->set('view', function () use($config) {
	$view = new App\Mvc\View();
	$engine = function ($view, $di) use($config) {
		$cacheDir = $config->application->cacheDir;
		if (!is_dir($cacheDir)) {
			mkdir($cacheDir, 0755, true);
		}
		$volt = new App\Mvc\View\Engine\Volt($view, $di);
		$volt->setOptions(array(
			'compiledPath' => $cacheDir . DIRECTORY_SEPARATOR, 
			'compiledSeparator' => '_', 
			'compileAlways' => false, 
			'compiledExtension' => '.php'
		));
		return $volt;
	};
	$view->registerEngines(array(
		'.html' => $engine,  // html扩展名的模板交由$engine模板引擎去处理。
		'.phtml' => $engine // phtml扩展名的模板也交由$engine模板引擎去处理。
	));
	$view->setRenderLevel(Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
	$view->setViewsDir(APP_ROOT . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR);
	return $view;
});

// 模型元数据服务。
$di->set('modelsMetaData', function () {
	return new Phalcon\Mvc\Model\MetaData\Memory();
});

// 模型管理服务。
$di->set('modelsManager', function () {
	return new App\Mvc\Model\Manager();
});

// 加密服务。
$di->set('crypt', function () {
	$crypt = new Phalcon\Crypt();
	$crypt->setCipher('rijndael-256');
	$crypt->setMode('cfb');
	$crypt->setKey('53a7d1b524a68eeb1fb4c589b5fc4522');
	return $crypt;
});

/*
 ***************************************************************************
 * 以下的设置可以随情况而更改。
 ***************************************************************************
 */

// 数据库连接服务。
$di->set('db', function () use($config) {
	$db = new Phalcon\Db\Adapter\Pdo\Mysql(array(
		'host' => $config->database->host, 
		'port' => $config->database->port, 
		'username' => $config->database->username, 
		'password' => $config->database->password, 
		'dbname' => $config->database->dbname
	));
	$db->execute('set names "utf8"');
	$db->execute('set sql_mode="PIPES_AS_CONCAT,IGNORE_SPACE,STRICT_ALL_TABLES"');
	return $db;
});
