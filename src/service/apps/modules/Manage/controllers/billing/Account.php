<?php

class Account extends Base
{
    public function add($params = [])
    {
        log_message('---'.__METHOD__.'---'.json_encode($params));
        if (!isTrueKey($params, 'status_tag_id')) {
            rsp_error_tips(10001, '状态标签信息');
        }
        if (!isTrueKey($params, 'billing_type_id')) {
            rsp_error_tips(10001, '计费类型信息');
        }
        if (!isset($params['billing_account_name']) || !is_string($params['billing_account_name'])) {
            rsp_error_tips(10001, '计费科目名称');
        }
        $params['billing_account_name'] = str_filter($params['billing_account_name']);
        if (mb_strlen($params['billing_account_name']) < 1) {
            rsp_error_tips(10001, '计费科目名称');
        } elseif (mb_strlen($params['billing_account_name']) > 10) {
            rsp_die_json(10001, '计费科目名称不能超过10个字符');
        }
        if (isset($params['remark'])) {
            if (!is_string($params['remark'])) {
                rsp_die_json(10001, 'remark 参数类型错误');
            } elseif (mb_strlen($params['remark']) > 300) {
                rsp_die_json(10001, '备注不能超过300个字符');
            }
        }
        //检查名称是否与系统默认计费类型冲突
        $tag_id = $this->getTagId($params['billing_account_name']);
        if ($tag_id === false) {
            rsp_die_json(10002, '标签信息查询失败');
        } elseif ($tag_id) {
            rsp_die_json(10003, '当前名称为系统默认的计费类型或科目名称，请修改');
        }
        if (!isTrueKey($params, 'billing_penalty_tag_id')) {
            rsp_error_tips(10001, '是否收取违约金信息');
        }
        if (!isset($params['billing_penalty_rule_id'])) {
            rsp_error_tips(10001, '收费规则信息');
        }
        if (!isset($params['billing_unit_tag_id'])) {
            rsp_error_tips(10001, '最小计费单位信息');
        }
        if (!isset($params['if_cycle_tag_id'])) {
            rsp_error_tips(10001, '是否周期计费信息');
        }
        if (!isset($params['billing_schedule_data'])) {
            rsp_error_tips(10001, '出账日计划任务信息');
        }
        if (!is_array($params['billing_schedule_data'])) {
            rsp_die_json(10001, 'billing_schedule_data 参数类型错误');
        }
        
        //收取违约金
        if ($params['billing_penalty_tag_id'] == 1420) {
            if (!isTrueKey($params, 'billing_penalty_grace_days', 'billing_penalty_grace_tag_id')) {
                rsp_die_json(10001, '违约周期信息');
            }
        }
        
        $params['billing_schedule_data'] = json_encode($params['billing_schedule_data']);
        $params['billing_account_id'] = resource_id_generator(self::RESOURCE_TYPES['billing_account']);
        if (!$params['billing_account_id']) {
            rsp_die_json(10005, '资源ID创建失败');
        }
        $params['created_by'] = $this->employee_id;
        $params['project_id'] = $this->project_id;
        $res = $this->billing->post('/billingAccount/add', $params);
        if ($res['code'] != 0) {
            rsp_die_json(10005, '添加失败，'.$res['message']);
        }
        rsp_success_json($res['content']);
    }
    
    public function update($params = [])
    {
        log_message('---'.__METHOD__.'---'.json_encode($params));
        if (!isTrueKey($params, 'billing_account_id')) {
            rsp_error_tips(10001, '计费科目ID');
        }
        $res = $this->billing->post('/billingAccount/simpleLists',
            ['billing_account_id' => $params['billing_account_id']]);
        if ($res['code'] != 0) {
            rsp_die_json(10002, '计费科目信息获取失败');
        } elseif (empty($res['content'])) {
            rsp_die_json(10008, '计费科目不存在');
        }
        $billing_account = array_pop($res['content']);
        if (isset($params['billing_account_name']) && is_string($params['billing_account_name'])) {
            $params['billing_account_name'] = str_filter($params['billing_account_name']);
            if (mb_strlen($params['billing_account_name']) < 1) {
                rsp_die_json(10001, '计费科目名称不能为空');
            } elseif (mb_strlen($params['billing_account_name']) > 10) {
                rsp_die_json(10001, '计费科目名称不能超过10个字符');
            }
            //检查名称是否与系统默认计费类型冲突
            $tag_id = $this->getTagId($params['billing_account_name']);
            if ($tag_id === false) {
                rsp_die_json(10002, '标签信息查询失败');
            } elseif ($tag_id && $billing_account['billing_account_tag_id'] != $tag_id) {
                rsp_die_json(10003, '当前名称为系统默认的计费类型或科目名称，请修改');
            }
        }
        if (isset($params['remark'])) {
            if (!is_string($params['remark'])) {
                rsp_die_json(10001, 'remark 参数类型错误');
            } elseif (mb_strlen($params['remark']) > 300) {
                rsp_die_json(10001, '备注不能超过300个字符');
            }
        }
        if (isset($params['billing_schedule_data'])) {
            $params['billing_schedule_data'] = json_encode($params['billing_schedule_data']);
        }
        $params['updated_by'] = $this->employee_id;
        $res = $this->billing->post('/billingAccount/update', $params);
        if ($res['code'] != 0) {
            rsp_die_json(10006, '更新失败，'.$res['message']);
        }
        //如果禁用了科目，则需要将科目的计费配置也同时禁用
        if (isTrueKey($params, 'status_tag_id') && $params['status_tag_id'] == 1380) {
            $res = $this->billing->post('/businessConfig/disableByAccount', [
                'billing_account_id' => $params['billing_account_id'],
                'updated_by' => $this->employee_id,
            ]);
            if (!isset($res['code']) || $res['code'] != 0) {
                rsp_die_json(10006, '禁用当前科目下的计费配置失败');
            }
        }
        rsp_success_json();
    }
    
    public function show($params = [])
    {
        log_message('---'.__METHOD__.'---'.json_encode($params));
        if (!isTrueKey($params, 'billing_account_id')) {
            rsp_error_tips(10001, 'billing_account_id');
        }
        $params['project_id'] = $this->project_id;
        $res = $this->billing->post('/billingAccount/lists', $params);
        if ($res['code'] != 0) {
            rsp_die_json(10002, '查询失败，'.$res['message']);
        } elseif (empty($res['content'])) {
            rsp_success_json();
        }
        $billing_account_info = array_pop($res['content']);
        //操作人
        $employee_ids = array_unique(array_filter([
            $billing_account_info['created_by'],
            $billing_account_info['updated_by'],
        ]));
        if (!empty($employee_ids)) {
            $res = $this->user->post('/employee/lists', ['employee_ids' => $employee_ids]);
            $employee_info = $res['code'] == 0 ? many_array_column($res['content'], 'employee_id') : [];
        } else {
            $employee_info = [];
        }
        $billing_account_info['created_by_name'] = getArraysOfvalue($employee_info, $billing_account_info['created_by'],
            'full_name');
        $billing_account_info['updated_by_name'] = getArraysOfvalue($employee_info, $billing_account_info['updated_by'],
            'full_name');
        //状态
        if ($billing_account_info['status_tag_id']) {
            $res = $this->tag->post('/tag/show', ['tag_id' => $billing_account_info['status_tag_id']]);
            $tag_info = $res['code'] == 0 ? $res['content'] : [];
        } else {
            $tag_info = [];
        }
        $billing_account_info['status_name'] = $tag_info['tag_name'] ?? '';
        //计费类型
        if ($billing_account_info['billing_type_id']) {
            $res = $this->billing->post('/billingType/lists', [
                'billing_type_id' => $billing_account_info['billing_type_id']
            ]);
            $billing_type_info = $res['code'] == 0 ? array_pop($res['content']) : [];
        } else {
            $billing_type_info = [];
        }
        $billing_account_info['billing_type_name'] = $billing_type_info['billing_type_name'] ?? '';
        //父级计费科目
        if ($billing_account_info['parent_billing_account_id']) {
            $res = $this->billing->post('/billingAccount/lists', [
                'billing_account_id' => $billing_account_info['parent_billing_account_id']
            ]);
            $parent_info = $res['code'] == 0 ? array_pop($res['content']) : [];
        } else {
            $parent_info = [];
        }
        $billing_account_info['parent_billing_account_name'] = $parent_info['billing_account_name'] ?? '';
        
        $billing_account_info['created_at'] = $billing_account_info['created_at']
            ? date('Y-m-d H:i:s', $billing_account_info['created_at'])
            : '';
        $billing_account_info['updated_at'] = $billing_account_info['updated_at']
            ? date('Y-m-d H:i:s', $billing_account_info['updated_at'])
            : '';
        rsp_success_json($billing_account_info);
    }
    
    public function tree($params = [])
    {
        log_message('---'.__METHOD__.'---'.json_encode($params));
        if (!isTrueKey($params, 'billing_type_id')) {
            rsp_error_tips(10001, '计费类型信息');
        }
        $params['project_id'] = $this->project_id;
        $res = $this->billing->post('/billingAccount/simpleLists', $params);
        if ($res['code'] != 0) {
            rsp_die_json(10002, '查询失败，'.$res['message']);
        } elseif (empty($res['content'])) {
            rsp_success_json();
        }
        $data = $this->recursion($res['content']);
        rsp_success_json($data);
    }
    
    private function recursion($data, $parent_billing_account_id = 0)
    {
        $arr = [];
        if (empty($data) || !is_array($data)) {
            return $arr;
        }
        foreach ($data as $value) {
            if ($value['parent_billing_account_id'] == $parent_billing_account_id) {
                $value['children'] = $this->recursion($data, $value['billing_account_id']);
                $arr[] = $value;
            }
        }
        return $arr;
    }
}