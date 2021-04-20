<?php

class Comm_Sms
{
    /**
     * @param $mobile
     * @param $template
     * @param $sms_params
     * @return array
     * 发送短信 旧版
     */
    public static function oldSendSms($mobile, $sms_params, $template = 'T170317005910')
    {
        $params = [
            'content' => json_encode($sms_params),
            'mobile' => $mobile,
            'tNum' => $template,
            'big_msg' => 1
        ];
        log_message(__FUNCTION__ . json_encode($params));

        $config = getConfig('ms.ini');
        $smsUrl = $config->aliyunSms->url ?? '';
        if ( !$smsUrl ) {
            return self::returnCode(10001, '推送失败:获取地址失败');
        }

        $tmp = self::request($smsUrl, '/sendSms', http_build_query($params), '', 'GET');
        $result = json_decode($tmp, true);
        $code = (int)$result['showapi_res_body']['ret_code'] ?? '';
        if ( $code !== 0 ) {
            return self::returnCode(10007, '发送失败');
        }
        return self::returnCode(0, '发送成功');
    }

    /**
     * @param $mobile
     * @param $template
     * @param $sms_params
     * @return array
     * 发送短信 旧版改由推送微服务去请求
     */
    public static function sendSms($mobile, $sms_params, $template = 'T170317005910')
    {
        $params = [
            'content' => '短信推送',
            'mobile' => $mobile,
            'channel' => ['sms'],
            'source' => 'code',
            'title' => '短信验证码',
            'sms_template_id' => 1,
            'sms_params' => json_encode($sms_params,JSON_UNESCAPED_UNICODE)
        ];
        log_message(__FUNCTION__ . json_encode($params));

        $obj = new Comm_Curl([ 'service'=>'msg','format'=>'json']);
        $sendResult = $obj->post('/pushmsg/singleUser',$params);
        if( empty($sendResult) ){
            return self::returnCode(10008,'请求接口响应异常');
        }
        if( $sendResult['code'] != 0 ){
            return self::returnCode(10007,$sendResult['message']);
        }
        return self::returnCode(0, '发送成功');
    }

    /**
     * @param int $code
     * @param string $msg
     * @param string $data
     * @return array
     * 返回提示码
     */
    protected static function returnCode($code = 0, $msg = 'success', $data = '')
    {
        return ['code' => $code, 'message' => $msg, 'content' => $data];
    }

    /**
     * @param $host
     * @param $path
     * @param string $querys
     * @param string $bodys
     * @param string $method
     * @param string $appcode
     * @param int $timeout
     * @return bool|string
     * 请求阿里云接口
     */
    protected static function request($host, $path, $querys = '', $bodys = '', $method = '', $appcode = '', $timeout = 5)
    {
        $config = getConfig('secret.ini');
        $getAppcode = $config->aliyun->appSecret ?? '';
        $appcode = $appcode ?: $getAppcode;
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        //根据API的要求，定义相对应的Content-Type
        array_push($headers, "Content-Type" . ":" . "application/x-www-form-urlencoded; charset=UTF-8");
        $url = $host . $path . "?" . $querys;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        if ( 1 == strpos("$" . $host, "https://") ) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);

        $result = curl_exec($curl);
        log_message(__FUNCTION__ . "【结果：】" . json_encode([$host, $path, $result]));
        return $result;
    }
}