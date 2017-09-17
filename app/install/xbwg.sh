#!/usr/bin/php
<?php
$cmd = '/usr/bin/mysqldump --host=127.0.0.1 -uroot -pos2017@db -x --hex-blob --databases xbwg_admins xbwg_manages xbwg_users xbwg_trades xbwg_mutexes xbwg_comments xbwg_comments_indexes xbwg_categories xbwg_goodses xbwg_carts xbwg_shops xbwg_utils xbwg_consults xbwg_products xbwg_miscs xbwg_smses xbwg_searches_0 xbwg_searches_1 xbwg_searches_2 xbwg_searches_3 xbwg_searches_4 xbwg_searches_5 xbwg_searches_6 xbwg_searches_7 xbwg_searches_8 xbwg_searches_9 xbwg_searches_a xbwg_searches_b xbwg_searches_c xbwg_searches_d xbwg_searches_e xbwg_searches_f xbwg_coupons xbwg_smses';
$sqlFileName = __DIR__ . '/xbwg_' . date('mdHi') . '.sql';
$cmd = "$cmd > $sqlFileName";
shell_exec($cmd);
echo $sqlFileName . "\n";
echo "done\n";
