#!/usr/bin/php
<?php
// $ls = shell_exec('ls ../www/app/modules/*/configs/config.php');
// $ar = explode("\n", rtrim($ls));

$ar[] = '../www/app/configs/config.php';
$ar[] = '../www/app/modules/kvCache/runtime/KVCacheCluster.db';
$ar[] = '../www/app/views/default/common/menuList.html';

$ar[] = '../www/public/pcindex.html';
$ar[] = '../www/public/appliance.html';
$ar[] = '../www/public/specialty.html';
$ar[] = '../www/public/koreaNew.html';
$ar[] = '../www/public/overseas.html';
$ar[] = '../www/public/wine.html';
$ar[] = '../www/public/topicMonthly.html';
$ar[] = '../www/public/topicMonthlyNew.html';
$ar[] = '../www/public/newUser.html';
$ar[] = '../www/public/car.html';
$ar[] = '../www/public/food.html';
$ar[] = '../www/public/beef.html';
$ar[] = '../www/public/perfume.html';
$ar[] = '../www/public/topicMay.html';
$ar[] = '../www/public/milk.html';

$ar[] = '../www/public/mobile/index.html';
$ar[] = '../www/public/mobile/views/html/category.html';

$ar[] = '../www/public/mobileMin/index.html';
$ar[] = '../www/public/mobileMin/views/html/category.html';

$cmds = array();
foreach ($ar as $file) {
    $file2 = substr($file, 7);
    $cmds[] = "cp $file $file2";
}

// $cmds[] = 'cp app ../www -r';
// $cmds[] = 'cp public ../www -r';

foreach ($cmds as $cmd) {
    echo $cmd . "\n";
    shell_exec($cmd);
}

echo "done\n";
