<?php

/**
 * create by wuli
 * Class Comm_Sign
 * 各个微服务或适配器api签名
 *
 */
class Comm_Sign
{
    /**
     * @param $params
     * @return bool
     * 系统统一登录 各个子系统SESSION设置
     */
    public static function a4wechat_make_sign($params)
    {
        $otherCfg = getConfig('secret.ini');
        $params['ts'] = time();
        $params['api_key'] = $otherCfg->get('a4wechat.appkey');
        $secret = $otherCfg->get('a4wechat.app_secret');

        ksort($params);
        $url_params = '';
        foreach ($params as $k => $v) {
            if (!is_array($v)) {
                $url_params .= $k . '=' . $v . '&';
            }
        }
        $url_params .= "secret={$secret}";
        log_message("【" . __FUNCTION__ . "】===url_params==" . $url_params);
        $params['api_signature'] = md5($url_params);
        return $params;
    }

}

