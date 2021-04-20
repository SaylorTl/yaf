<?php


class Comm_Gateway
{
    public function gateway($send_data, $method, $service = [])
    {
        $data = [
            'format' => 'JSON',
            'charset' => 'utf-8',
            'timestamp' => time(),
            'app_id' => $_SESSION['oauth_app_id'],
            'token' => session_id(),
            'method' => $method,
            'biz_content' => json_encode($send_data, JSON_UNESCAPED_UNICODE)
        ];
        $obj = new Comm_Curl($service);
        $result = $obj->post('/gateway', $data);
        if ( empty($result) ) {
            return ['code' => 10007, 'message' => '请求失败', 'content' => ''];
        }
        return $result;
    }
}