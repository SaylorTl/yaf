<?php

//测试
$test = [
    'et_admin.test' => ['module' => 'index', 'controller' => 'Index', 'action' => 'index']
];

$pm_map = require(CONFIG_PATH . "/method/pmMethodMap.php");
$tag_map = require(CONFIG_PATH . "/method/tagMethodMap.php");
$addr_map = require(CONFIG_PATH . "/method/addrMethodMap.php");
$company_map = require(CONFIG_PATH . "/method/companyMethodMap.php");
$files_map = require(CONFIG_PATH . "/method/filesMethodMap.php");
$user_map = require(CONFIG_PATH . "/method/userMethodMap.php");
$resource_map = require(CONFIG_PATH . "/method/resourceMethodMap.php");
$agreement_map = require(CONFIG_PATH . "/method/agreementMethodMap.php");
$adv_map = require(CONFIG_PATH . "/method/advMethodMap.php");
$car_map = require(CONFIG_PATH . "/method/carMethodMap.php");
$module_map = require(CONFIG_PATH . "/method/moduleMethodMap.php");
$permission_map = require(CONFIG_PATH . "/method/permissionMethodMap.php");
$tips_map = require(CONFIG_PATH . "/method/tipsMethodMap.php");
$adapter_map = require(CONFIG_PATH . "/method/adapterMethodMap.php");
$qrcode_map = require(CONFIG_PATH . "/method/qrcodeMethodMap.php");
$tiding_map = require(CONFIG_PATH . "/method/tidingMethodMap.php");
$auth_map = require(CONFIG_PATH . "/method/oauthMethodMap.php");
$payment_map = require(CONFIG_PATH . "/method/paymentMethodMap.php");
$ignore_map = require(CONFIG_PATH . "/method/ignoreMethodMap.php");
$pos_map = require(CONFIG_PATH . "/method/posMethodMap.php");
$integral_map = require(CONFIG_PATH . "/method/integralMethodMap.php");
$device_map = require(CONFIG_PATH . "/method/deviceMethodMap.php");
$lock_map = require(CONFIG_PATH . "/method/lockMethodMap.php");
$msg_map = require(CONFIG_PATH . "/method/msgMethodMap.php");
$charge_map = require(CONFIG_PATH . "/method/chargeMethodMap.php");
$wos_map = require(CONFIG_PATH . "/method/wosMethodMap.php");
$log_map = require(CONFIG_PATH . "/method/logMethodMap.php");
$billing_map = require(CONFIG_PATH . "/method/billingMethodMap.php");
$rule_map = require(CONFIG_PATH . "/method/ruleMethodMap.php");
$oa_map = require(CONFIG_PATH . "/method/oaMethodMap.php");
$redis_map = require(CONFIG_PATH . "/method/redisMethodMap.php");
$parking_map = require(CONFIG_PATH . "/method/parkingMethodMap.php");
$report_map = require(CONFIG_PATH . "/method/reportMethodMap.php");


$maps = array_merge($test, $pm_map, $tag_map, $addr_map, $company_map,$permission_map,
    $files_map, $user_map, $resource_map, $agreement_map, $adv_map,$car_map,$module_map,
    $tips_map,$adapter_map,$qrcode_map,$tiding_map,$auth_map,$payment_map,$ignore_map,$pos_map
    ,$integral_map,$device_map,$lock_map,$msg_map,$charge_map,$wos_map,$log_map,$billing_map,
    $rule_map,$oa_map,$redis_map,$parking_map,$report_map);

return $maps;
