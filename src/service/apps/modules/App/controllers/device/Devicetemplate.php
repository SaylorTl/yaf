<?php

class Devicetemplate extends Base
{
    
    public function lists($params = [])
    {
        if (!isTrueKey($params, 'page', 'pagesize')) {
            rsp_error_tips(10001, 'page pagesize');
        }
        
        $lists = $this->device->post('/device/template/lists', $params);
        if ($lists['code'] !== 0 || !$lists['content']) {
            rsp_success_json(['total' => 0, 'lists' => []]);
        }
        
        unset($params['page'], $params['pagesize']);
        $total = $this->device->post('/device/template/count', $params);
        if ($total['code'] !== 0 || !$total['content']) {
            rsp_success_json(['total' => 0, 'lists' => $lists['content']]);
        }
        
        // 供应商
        $vendor_ids = array_unique(array_filter(array_column($lists['content'], 'vendor_id')));
        $vendors = $this->device->post('/vendor/lists', ['vendor_ids' => $vendor_ids]);
        $vendors = ($vendors['code'] === 0 && $vendors['content']) ? many_array_column($vendors['content'],
            'vendor_id') : [];
        
        // 员工
        $employee_ids = array_unique(array_merge(array_filter(array_column($lists['content'], 'created_by')),
            array_filter(array_column($lists['content'], 'updated_by'))));
        $employees = $this->user->post('/employee/lists', ['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'],
            'employee_id') : [];
        
        $data = array_map(function ($m) use ($employees, $vendors) {
            $m['vendor_name'] = getArraysOfvalue($vendors, $m['vendor_id'], 'vendor_name');
            $m['created_by'] = getArraysOfvalue($employees, $m['created_by'], 'full_name');
            $m['updated_by'] = getArraysOfvalue($employees, $m['updated_by'], 'full_name');
            return $m;
        }, $lists['content']);
        rsp_success_json(['total' => (int)$total['content'], 'lists' => $data]);
    }
    
    public function show($params = [])
    {
        if (!isTrueKey($params, ...['device_template_id'])) {
            rsp_error_tips(10001);
        }
        $lists = $this->device->post('/device/template/lists', [
            'device_template_id' => $params['device_template_id'],
            'page' => 1,
            'pagesize' => 1,
        ]);
        if ($lists['code'] !== 0 || !$lists['content']) {
            rsp_success_json([]);
        }
        rsp_success_json($lists['content'][0]);
    }
}

