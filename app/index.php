<?php
// 唯一全局函数。
function is_empty($param) {
	return empty($param);
}

// 设置错误报告。
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 运行应用程序。
include __DIR__ . '/libraries/App/System.php';
App\System::run();
