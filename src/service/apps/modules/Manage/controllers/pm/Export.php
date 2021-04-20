<?php

final class Export extends Base
{
    public function msg($params = [])
    {
        if (!isTrueKey($params, 'file_id', 'type')) rsp_die_json(10001, '缺少参数');
        $params['employee_id'] = $this->employee_id;
        $res = $this->lumenscript->post('/export/msg', $params);
        if ($res['code'] !== 0) rsp_die_json(10002, $res['message']);
        rsp_success_json(1);
    }

    public function status($params = [])
    {
        if (!isTrueKey($params, 'file_id')) rsp_die_json(10001, '缺少参数');
        $res = $this->lumenscript->post('/export/status', ['file_id' => $params['file_id']]);
        if ($res['code'] !== 0) rsp_die_json(10002, $res['message']);
        rsp_success_json($res['content']);
    }
}