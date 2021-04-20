<?php

class Comm_Pay
{
    public static function clearToken($app_id)
    {
        $oauth_app_id = $app_id;

        $redis = Comm_Redis::getInstance();
        $redis->select(8);
        $key = 'PAY_'.$oauth_app_id;
        $redis->del($key);
        return true;
    }

    public static function getToken($app_id)
    {
        // config
        $config = getConfig('ms.ini');
        $pay_config = getConfig('pay.ini');
        $auth_url = $config->auth2->url;
        $oauth_app_id = $app_id;
        $oauth_secret = $pay_config->oauth->$app_id->secret;

        //
        $redis = Comm_Redis::getInstance();
        $redis->select(8);
        $key = 'PAY_'.$oauth_app_id;
        if ($token = $redis->get($key)) {
            return $token;
        }

        // 获取code
        $data = [
            'oauth_app_id'=>$oauth_app_id,
            'ts' => time(),
        ];

        // 签名
        ksort($data);
        $url_params = '';
        foreach ($data as $k => $v) {
            if( !is_array($v) ) $url_params .= $k.'='.$v.'&';
        }
        $url_params .= 'secret='.$oauth_secret;
        $data['signature'] = md5($url_params);

        //
        $result = curl_text('POST',$auth_url.'/silent/auth/code',$data);
        $get_code_info = json_decode($result,true);
        if( !isset($get_code_info['code']) || !isset($get_code_info['content']) ) {
            log_message(__METHOD__ . '----' . json_encode($get_code_info,JSON_UNESCAPED_UNICODE));
            return '';
        }
        if($get_code_info['code'] != 0 || !$get_code_info['content']){
            log_message(__METHOD__ . '----' . json_encode($get_code_info,JSON_UNESCAPED_UNICODE));
            return '';
        }
        $code = $get_code_info['content']['code'] ?? '';
        $app_id = $get_code_info['content']['app_id'] ?? '';
        $scope = $get_code_info['content']['scope'] ?? '';

        // 获取access_token
        $token_param = [
            'oauth_app_id' => $oauth_app_id,
            'code' => $code,
            'third_party_app_id' => $app_id,
            'scope' => $scope
        ];
        $result = curl_text('POST',$auth_url.'/silent/auth/access_token',$token_param);
        $access_token_result = json_decode($result,true);
        if( $access_token_result['code'] == 0 && !empty($access_token_result['content']['access_token']) ){
            $access_token = $access_token_result['content']['access_token'];
            $redis->set($key,$access_token);
            $redis->expire($key,3600);
            return $access_token;
        }
        return '';
    }

    public static function gateway($method, $biz_content)
    {
        $app_id = $_SESSION['oauth_app_id'];
        $pay_config = getConfig('pay.ini');
        $res = curl_json('POST',$pay_config->pay->url.'/gateway',[
            'app_id' => $app_id,
            'method' => $method,
            'format' => 'json',
            'charset' => 'utf-8',
            'timestamp' => time(),
            'token' => self::getToken($app_id),
            'biz_content' => is_array($biz_content) ? json_encode($biz_content) : $biz_content,
        ]);
        if (intval($res['code']) === 90000) {
            self::clearToken($app_id);
            return curl_json('POST',$pay_config->pay->url.'/gateway',[
                'app_id' => $app_id,
                'method' => $method,
                'format' => 'json',
                'charset' => 'utf-8',
                'timestamp' => time(),
                'token' => self::getToken($app_id),
                'biz_content' => is_array($biz_content) ? json_encode($biz_content) : $biz_content,
            ]);
        }
        return $res;
    }
}