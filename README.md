<<<<<<< HEAD
# 1123V2Service

``` bash
cd 1123V2Serivce
composer require phalcon/devtools
composer require predis/predis
composer require guzzle/guzzle
composer require --dev snowair/phalcon-debugbar
```

# 配置devtools

## 配置全局phalcon

``` bash
./vendor/phalcon/devtools/phalcon.sh
sudo ln -s $(pwd)/vendor/phalcon/devtools/phalcon.php /usr/bin/phalcon
sudo chmod ugo+x /usr/bin/phalcon
```
# 修改runtime权限

``` bash
mkdir app/runtime/debugbar
chmod -R 0777 app/runtime
```

# debugbar配置

``` php
$di['app'] = $app;
$provider = new \Snowair\Debugbar\ServiceProvider(APP_ROOT . '/configs/debugbar.php');
$provider -> register();//注册
$provider -> boot(); //启动
```

#解决bug
###解决sql导入问题
``` bash
sed -ie 's/row_format=fixed//g' xbwg.sql
```
=======
# 1123V2Service
>>>>>>> 1ab78fab1b4cd14e8301a2a7d727d582db4631e2
