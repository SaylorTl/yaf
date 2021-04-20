<?php

final class Import extends Base
{
    public function msg($params = [])
    {
        if (!isTrueKey($params, 'file_id', 'type')) rsp_die_json(10001, '缺少参数');
        $params['employee_id'] = $this->employee_id;
        $params['project_id'] = $this->project_id;
        $res = $this->lumenscript->post('/import/msg', $params);
        if ($res['code'] !== 0) rsp_die_json(10002, $res['message']);
        rsp_success_json(1);
    }

    public function status($params = [])
    {
        if (!isTrueKey($params, 'file_id')) rsp_die_json(10001, '缺少参数');
        $res = $this->lumenscript->post('/import/status', ['file_id' => $params['file_id']]);
        if ($res['code'] !== 0) rsp_die_json(10002, $res['message']);
        rsp_success_json($res['content']);
    }

    public function history($params = [])
    {
        if (!$this->employee_id) rsp_die_json(10001, 'employee_id缺失');
        $res = $this->lumenscript->post('/import/history', ['employee_id' => $this->employee_id]);
        if ($res['code'] !== 0) rsp_die_json(10002, $res['message']);
        if (empty($res['content'])) rsp_success_json([]);

        // 员工
        $employee_ids = array_filter(array_column($res['content'], 'employee_id'));
        $employees = $this->user->post('/employee/lists', ['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        $list = array_map(function ($m) use ($employees) {
            $m['msg_time'] = isTrueKey($m, 'msg_time') ? date('Y-m-d H:i:s', $m['msg_time']) : '';
            $m['end_time'] = isTrueKey($m, 'end_time') ? date('Y-m-d H:i:s', $m['end_time']) : '';
            $m['employee_name'] = getArraysOfvalue($employees, $m['employee_id'], 'full_name');
            return $m;
        }, $res['content']);
        rsp_success_json($list);
    }
}