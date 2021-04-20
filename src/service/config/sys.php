<?php
date_default_timezone_set("PRC");
@ini_set('default_charset', 'UTF-8');

define('ERROR_LOG_FILE',SERVICE_PATH."/data/logs/error-".date('Y-m-d').".log");

//日志配置
@ini_set("display_errors",on);
@ini_set("error_reporting",E_ALL);
@ini_set("log_errors",1);
@ini_set('error_log',ERROR_LOG_FILE);

//header('Access-Control-Allow-Origin:*');
if(isset($_SERVER['HTTP_ORIGIN'])){
    header('Access-Control-Allow-Origin:'.$_SERVER['HTTP_ORIGIN']);
}
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Access-Token");

//header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header('Access-Control-Allow-Credentials:true');
header('Access-Control-Max-Age: 3600');




?>
