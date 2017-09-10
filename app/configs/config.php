<?php
// 公用配置。
$config = new Phalcon\Config(array(
	'dynamicConfigIdentifier' => 'service',
	'dynamicConfigRefreshInterval' => 60,
	'timezone' => 'Asia/Shanghai',
	'application' => array(
		'baseUri' => '/',
		'cacheDir' => __DIR__ . '/../../app/runtime/cache'
	),
	'database' => array(
		'adapter' => 'Mysql',
		'host' => 'localhost',
		'port' => '3309',
		'username' => 'root',
		'password' => '?wg1985?',
		'dbname' => 'mysql'
	),
	'_remoteServices' => array(
		'demo' => array(
			'url' => 'http://192.168.10.221/demo',
			'key' => 'AA443646K3NKLKLK43KLEKLK4356KLGF'
		)
	),
	'listenServices' => array(
		'demo' => array(
			'url' => 'http://192.168.10.221/demo',
			'key' => 'AA443646K3NKLKLK43KLEKLK4356KLGF'
		)
	)
));
