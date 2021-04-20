<?php
define('BASE_PATH', realpath(dirname(__FILE__) . '/..'));
define('SERVICE_PATH', BASE_PATH.'/service');
define('PUBLIC_PATH', BASE_PATH.'/public');
define('CONFIG_PATH', SERVICE_PATH.'/config');
define('APP_PATH',SERVICE_PATH.'/apps');
define('DATA_PATH',SERVICE_PATH.'/data');

define('APP_MODULE_PATH',APP_PATH.'/modules/App');
define('MANAGE_MODULE_PATH',APP_PATH.'/modules/Manage');
define('RULE_MODULE_PATH',APP_PATH.'/modules/Rule');
$app  = new Yaf_Application(CONFIG_PATH . "/application.ini");


$loader = Yaf_Loader::getInstance();
$loader->registerLocalNamespace( array("Comm") );
require CONFIG_PATH.'/sys.php';         //系统设置
require BASE_PATH.'/service/library/function.php';        //全局通用函数
require BASE_PATH.'/service/data/vendor/autoload.php';    //第三方库
Yaf_Registry::set('config', Yaf_Application::app()->getConfig());
//初始化：连接jaegertracing,并将trace示例设置到全局变量
Tool_YafTracer::setTracer();
$app->bootstrap()->run();
?>
