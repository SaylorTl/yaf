<?php
final class Reader extends Base
{
    public function user($params = [])
    {
        if (!isTrueKey($params, ...['page', 'pagesize', 'employee_id'])) rsp_error_tips(10001);
        $logs = $this->log->get('/logs/show', [
            'kind' => 'user_action',
            'sid' => $params['employee_id'],
            'page' => $params['page'],
            'page_size' => $params['pagesize'],
        ]);
        if ($logs['code'] !== 0) rsp_success_json(['total'=>0,'lists'=>[]]);
        rsp_success_json(['total'=>$logs['content']['count'],'lists'=>$logs['content']['rows']]);
}

    public function resource($params = [])
    {
        if (!isTrueKey($params, ...['page', 'pagesize', 'kind', 'sid'])) rsp_error_tips(10001);
        $logs = $this->log->get('/logs/show', [
            'kind' => $params['kind'],
            'sid' => $params['sid'],
            'page' => $params['page'],
            'page_size' => $params['pagesize'],
        ]);
        if ($logs['code'] !== 0) rsp_success_json(['total'=>0,'lists'=>[]]);
        $this->perfectData($logs['content']['rows']);
        rsp_success_json(['total'=>$logs['content']['count'],'lists'=>$logs['content']['rows']]);
    }

    public function latest($params = [])
    {
        if (!isTrueKey($params, ...['page', 'pagesize'])) rsp_error_tips(10001);
        $where = [
            'page' => $params['page'],
            'page_size' => $params['pagesize'],
        ];
        if (isTrueKey($params,'kind')) $where['kind'] = $params['kind'];
        $logs = $this->log->get('/logs/lists', $where);
        if ($logs['code'] !== 0) rsp_success_json(['total'=>0,'lists'=>[]]);
        $this->perfectData($logs['content']['rows']);
        rsp_success_json(['total'=>$logs['content']['count'],'lists'=>$logs['content']['rows']]);
    }
    
    private function perfectData(&$data)
    {
        $employee_ids = array_filter(array_unique(array_column($data, 'username')));
        if (empty($employee_ids)) {
            $employee_data = [];
        } else {
            $res = $this->user->post('/employee/lists', ['employee_ids' => $employee_ids]);
            $employee_data = $res['code'] == 0 ? array_column($res['content'], null, 'employee_id') : [];
        }
        $data = array_map(function ($m) use ($employee_data) {
            $m['employee_id'] =  $m['username'];
            $m['employee_name'] = getArraysOfvalue($employee_data, $m['username'], 'full_name');
            $m['create_at'] = $m['create_at'] ? date('Y-m-d H:i:s',$m['create_at']) : '';
            $m['operation_time'] = $m['operation_time'] ? date('Y-m-d H:i:s',$m['operation_time']) : '';
            $m['submission_time'] = $m['submission_time'] ? date('Y-m-d H:i:s',$m['submission_time']) : '';
            return $m;
        }, $data);
    }
}