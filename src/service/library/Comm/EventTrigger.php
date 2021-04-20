<?php

/**
 * 事件触发器
 * Class Comm_EventTrigger
 */
class Comm_EventTrigger
{
    /**
     * 推送到事件触发器
     * @param  string  $event_name  事件名称（事件需要提前注册）
     * @param  array  $data  推送数据
     * @return bool
     */
    public static function push($event_name, $data)
    {

        try {
            $event_name = trim($event_name);
            if (!$event_name) {
                log_message('---Comm_EventTrigger/'.__FUNCTION__.'----'.json_encode([
                        'error' => '事件名称错误',
                        'event_name' => $event_name,
                    ], JSON_UNESCAPED_UNICODE));
                return false;
            }
            $sign = self::signature($data);
            if (!$sign) {
                return false;
            }
            $event_trigger_url = getConfig('ms.ini')->get('event_trigger.url');
            $result = curl_json("post", $event_trigger_url.'/event?evt_name='.$event_name.'&evt_token='.$sign, json_encode($data),["Content-Type:application/json"]);
            log_message('---Comm_EventTrigger/'.__FUNCTION__.'----'.json_encode([
                    'result' => $result,
                    'sign'=>$sign,
                    'data'=>$data
                ], JSON_UNESCAPED_UNICODE));
            if (!isset($result['code']) || $result['code'] != 0) {
                log_message('---Comm_EventTrigger/'.__FUNCTION__.'----'.json_encode([
                        'error' => '事件发布失败',
                    ], JSON_UNESCAPED_UNICODE));
                return false;
            }
            return true;
        } catch (Exception $e) {
            log_message('---Comm_EventTrigger/'.__FUNCTION__.'----'.json_encode([
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'error' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE));
            return false;
        }
    }
    /**
     * 签名
     * @param $data
     */
    private static function signature($data)
    {
        try {
            $cfg = getConfig('other.ini');
            $data['evt_key'] = $cfg->get('evt.key');
            ksort($data);
            $str = '';
            foreach ($data as $key => $value) {
                if(is_array($value)){
                    continue;
                }else{
                    $str .= $key.'='.$value.'&';
                }
            }
            return md5(trim($str.'evt_secret='.$cfg->get('evt.secret')));
        } catch (Exception $e) {
            log_message('---Comm_EventTrigger/'.__FUNCTION__.'----'.json_encode([
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'error' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE));
            return false;
        }
    }
}