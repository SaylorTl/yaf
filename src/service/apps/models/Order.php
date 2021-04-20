<?php

class OrderModel
{
    /**
     * redis key 记录计费得到的应付总金额，下单需校验金额
     */
    const PAY_AMOUNT_REDIS_KEY = 'SQY_MIDDLEWARE:CHARGING_TOTAL_PAY_AMOUNT';

    public $access;
    public $route;
    public $user;
    public $redis;

    public static function new_order_sn($params = []){
        $new_sn = new Comm_Curl([ 'service'=>'sn','format'=>'text' ]);
        $sn_url = getConfig('ms.ini')->get('sn.url');
        $get_sn = curl_json("get", $sn_url,[]);

        return preg_match("/\d{10,}$/",$get_sn) ? $get_sn."10037" : false;
    }

}