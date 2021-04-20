<?php

final class Message extends Base
{
    public function send($params = [])
    {
        if (!isTrueKey($params, ...['device_id', 'cmd'])) rsp_error_tips(10001);
        // 检测当前时间是否在开放时间段内
        if (isTrueKey($params, 'project_id')) {
            $show = $this->pm->post('/project/show', ['project_id' => $params['project_id']]);
            if ($show['code'] !== 0 || !$show['content']) {
                rsp_die_json(10002, '查询项目信息失败');
            }
            $begin = $show['content']['arrival_opening_begin_time'];
            $end = $show['content']['arrival_opening_end_time'];
            $time = date("H:i:s");
            if ($begin > $end && ($end < $time && $time < $begin)) {
                rsp_die_json(10016, '该时间段暂不支持扫码进场');
            }
            if ($begin < $end && ($time < $begin || $time > $end)) {
                rsp_die_json(10016, '该时间段暂不支持扫码进场');
            }
        }

        $params = (new \Device\MessageModel($params))->filter();
        $res = $this->device->post('/message/send', $params);
        if ($res['code'] !== 0) rsp_die_json(10001, $res['message']);
        rsp_success_json($res['content']);
    }

    public function calling($params = [])
    {
        if (!isTrueKey($params, ...['callId'])) rsp_error_tips(10001);
        $redis = \Comm_Redis::getInstance();
        $key = getConfig('redis.ini')->redis->list->keys->call_message;
        $data = $redis->get($key . $params['callId']);
        rsp_success_json(!!$data);
    }
}