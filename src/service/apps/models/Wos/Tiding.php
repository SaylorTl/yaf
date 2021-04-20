<?php

namespace Wos;

use \Comm_Curl;

class TidingModel
{
    public static function push($params)
    {
        $tiding = new Comm_Curl(['service' => 'tiding', 'format' => 'json']);
        $to = array_map(function ($m) {
            return array_unique(array_filter($m));
        }, array_merge_recursive($params['audience'] ?? [], array_map(function ($m) {
            return [$m];
        }, $params['performer'] ?? [])));
        
        if (empty($to)) {
            return;
        }
        
        log_message("接收消息人:".json_encode($to, 1));
        $tiding->post('/tiding/add', [
            'sid' => $params['_id'],
            'kind' => $params['tiding_type'],
            'title' => $params['title'],
            'initiator' => $params['initiator'],
            'audience' => array_map(function ($m) {
                return array_unique($m);
            }, $to),
            'details' => $params['multipart']['content'] ?? ''
        ]);
    }
}