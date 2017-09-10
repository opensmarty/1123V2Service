<?php
// 模块配置，注意：权重值为大于等于10的整数值。
$config = new Phalcon\Config(array(
	'virtualServersCount' => 256, 
	'realServersList' => array(
		array(
			'host' => '127.0.0.1', 
			'port' => '11211',
			'weight' => 10
		), 
		array(
			'host' => 'localhost', 
			'port' => '11211',
			'weight' => 15
		)
	)
));