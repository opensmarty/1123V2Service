<?php
// 唯一全局函数。
function is_empty($param) {
	return empty($param);
}

// 设置错误报告。
ini_set('display_errors', 0); // 设置为0可避免显示重复的错误提示。
error_reporting(E_ALL);

// 设置模拟HTTP请求的参数。
if (!isset($argv[1])) {
	$argv[1] = '/';
}
$queryString = '/' . str_replace('\+', '+', preg_replace('#\?|(?<!\\\\)\\+#', '&', ltrim($argv[1], '/')));
$_SERVER = array(
	'HTTP_X_REAL_IP' => '127.0.0.1', 
	'HTTP_X_FORWARDED_FOR' => '127.0.0.1', 
	'HTTP_HOST' => '127.0.0.1', 
	'HTTP_CONNECTION' => 'close', 
	'HTTP_ACCEPT' => 'text/html, application/xhtml+xml, */*', 
	'HTTP_ACCEPT_LANGUAGE' => 'zh-CN', 
	'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0)', 
	'HTTP_ACCEPT_ENCODING' => 'gzip, deflate', 
	'HTTP_COOKIE' => null, 
	'PATH' => null, 
	'COMSPEC' => null, 
	'PATHEXT' => null, 
	'SERVER_SIGNATURE' => '', 
	'SERVER_SOFTWARE' => 'Apache/2.2.27 (Win32) PHP/5.4.31 mod_ssl/2.2.27 OpenSSL/0.9.8za', 
	'SERVER_NAME' => '127.0.0.1', 
	'SERVER_ADDR' => '127.0.0.1', 
	'SERVER_PORT' => '80', 
	'REMOTE_ADDR' => '127.0.0.1', 
	'DOCUMENT_ROOT' => realpath(__DIR__ . '/../public'), 
	'SERVER_ADMIN' => 'xjx_0909@163.com', 
	'SCRIPT_FILENAME' => __FILE__, 
	'REMOTE_PORT' => '1599', 
	'GATEWAY_INTERFACE' => 'CGI/1.1', 
	'SERVER_PROTOCOL' => 'HTTP/1.0', 
	'REQUEST_METHOD' => 'GET', 
	'QUERY_STRING' => '_url=' . $queryString, 
	'REQUEST_URI' => '/index.php?_url=' . $queryString, 
	'SCRIPT_NAME' => '/index.php', 
	'PHP_SELF' => '/index.php', 
	'REQUEST_TIME_FLOAT' => time() . substr(microtime(), 1, 4), 
	'REQUEST_TIME' => time()
);
$args = explode('&', $_SERVER['QUERY_STRING']);
foreach ($args as $arg) {
	list($key, $val) = explode('=', $arg);
	$_GET[$key] = $val;
}

// 运行应用程序。
include __DIR__ . '/libraries/App/System.php';
App\System::run(DIRECTORY_SEPARATOR == '\\');
