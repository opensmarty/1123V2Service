<?php
// 模块配置。
$config = new Phalcon\Config(array(
	// 会话生命期秒数，超过这个时间没有访问时，会话就会到期，同时相关数据将被自动清除，当它小于等于0时代表使用会话默认到期时间。
	'lifeTime' => 3600, 
	'cookieName' => 'PHPSESSIONID', 
	'autoStart' => true
));
