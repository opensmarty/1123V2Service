<?php
// 唯一全局函数。
function is_empty($param) {
	return empty($param);
}

// 设置错误报告。
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 根据文件的路径，适当的调整引入的相对路径
require __DIR__.'/../vendor/autoload.php';

// 运行应用程序。
require_once __DIR__ . '/libraries/App/System.php';
App\System::run();
