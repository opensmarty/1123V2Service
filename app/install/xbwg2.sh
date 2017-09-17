#!/usr/bin/php
<?php
$cmd = '/usr/bin/mysqldump --host=127.0.0.1 -uroot -pos20172@db -x --hex-blob --databases xbwg_orders';
$sqlFileName = __DIR__ . '/xbwg_' . date('mdHi') . '.sql';
$cmd = "$cmd > $sqlFileName";
shell_exec($cmd);
echo $sqlFileName . "\n";
echo "done\n";
