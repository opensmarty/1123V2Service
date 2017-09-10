<?php
// 模块配置，注意：权重值为大于等于10的整数值。
$config = new Phalcon\Config(array(
	'virtualServersCount' => 256, 
	'realServersList' => array(
		array(
			'host' => '127.0.0.1', 
			'port' => '6379', 
			'weight' => 10
		), 
		array(
			'host' => 'localhost', 
			'port' => '6379', 
			'weight' => 15
		)
	)
));