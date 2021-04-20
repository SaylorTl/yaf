<?php

namespace Car;

use \Comm_Curl;
use Exception;

class UnlicensedModel
{
    /**
     * @param $message
     * @param $params
     * @throws Exception
     */
    public static function open(&$message, $params)
    {
        $space = (new Comm_Curl(['service' => 'pm', 'format' => 'json']))
            ->post('/space/show', ['space_id' => $params['space_id']]);
        if (!$space || $space['code'] = 0 || empty($space['content'])) {
            throw new Exception('空间信息不存在');
        }
        $project_id = $space['content']['project_id'];
        // 用户信息
        $user_id = $_SESSION['user_id'];
        $client_id = $_SESSION['client_id'];
        $mobile = $_SESSION['user_mobile'];

        $message['project_id'] = $project_id;
        $message['attach'] = [
            'through_id' => '',
            'user_id' => $user_id,
            'mobile' => $mobile,
            'project_id' => $project_id,
            'space_id' => $params['space_id'],
            'client_id' => $client_id,
            // 'arrival_at' => time(),
        ];
    }
}