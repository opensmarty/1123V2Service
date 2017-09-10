<?php
// 键值缓存服务。
$di->setShared('kvcache', 'App\\KvCache\\Servicers\\KVCacheCluster');