<?php

class Type extends Base
{
    public function add($params = [])
    {
        log_message('---'.__METHOD__.'---'.json_encode($params));
        if (!isTrueKey($params, 'status_tag_id')) {
            rsp_error_tips(10001, '状态标签信息');
        }
        if (!isset($params['billing_type_name']) || !is_string($params['billing_type_name'])) {
            rsp_error_tips(10001, '计费类型名称');
        }
        $params['billing_type_name'] = str_filter($params['billing_type_name']);
        if (mb_strlen($params['billing_type_name']) < 1) {
            rsp_error_tips(10001, '计费类型名称');
        } elseif (mb_strlen($params['billing_type_name']) > 10) {
            rsp_die_json(10001, '计费类型名称不能超过10个字符');
        }
        if (isset($params['remark'])) {
            if (!is_string($params['remark'])) {
                rsp_die_json(10001, 'remark 参数类型错误');
            } elseif (mb_strlen($params['remark']) > 300) {
                rsp_die_json(10001, '备注不能超过300个字符');
            }
        }
        //检查名称是否与系统默认计费类型冲突
        $tag_id = $this->getTagId($params['billing_type_name']);
        if ($tag_id === false) {
            rsp_die_json(10002, '标签信息查询失败');
        } elseif ($tag_id) {
            rsp_die_json(10003, '当前名称为系统默认的计费类型或科目名称，请修改');
        }
        $params['billing_type_id'] = resource_id_generator(self::RESOURCE_TYPES['billing_type']);
        if (!$params['billing_type_id']) {
            rsp_die_json(10005, '资源ID创建失败');
        }
        $params['created_by'] = $this->employee_id;
        $params['project_id'] = $this->project_id;
        $res = $this->billing->post('/billingType/add', $params);
        if ($res['code'] != 0) {
            rsp_die_json(10005, '添加失败，'.$res['message']);
        }
        rsp_success_json($res['content']);
    }
    
    public function update($params = [])
    {
        log_message('---'.__METHOD__.'---'.json_encode($params));
        if (!isTrueKey($params, 'billing_type_id')) {
            rsp_error_tips(10001, '计费类型ID');
        }
        $res = $this->billing->post('/billingType/simpleLists', ['billing_type_id' => $params['billing_type_id']]);
        if ($res['code'] != 0) {
            rsp_die_json(10002, '计费类型信息获取失败');
        } elseif (empty($res['content'])) {
            rsp_die_json(10008, '计费类型不存在');
        }
        $billing_type = array_pop($res['content']);
        if (isset($params['billing_type_name']) && is_string($params['billing_type_name'])) {
            $params['billing_type_name'] = str_filter($params['billing_type_name']);
            if (mb_strlen($params['billing_type_name']) < 1) {
                rsp_die_json(10001, '计费类型名称不能为空');
            } elseif (mb_strlen($params['billing_type_name']) > 10) {
                rsp_die_json(10001, '计费类型名称不能超过10个字符');
            }
            //检查名称是否与系统默认计费类型冲突
            $tag_id = $this->getTagId($params['billing_type_name']);
            if ($tag_id === false) {
                rsp_die_json(10002, '标签信息查询失败');
            } elseif ($tag_id && $billing_type['billing_type_tag_id'] != $tag_id) {
                rsp_die_json(10003, '当前名称为系统默认的计费类型或科目名称，请修改名称');
            }
        }
        if (isset($params['remark'])) {
            if (!is_string($params['remark'])) {
                rsp_die_json(10001, 'remark 参数类型错误');
            } elseif (mb_strlen($params['remark']) > 300) {
                rsp_die_json(10001, '备注不能超过300个字符');
            }
        }
        $params['updated_by'] = $this->employee_id;
        $res = $this->billing->post('/billingType/update', $params);
        if ($res['code'] != 0) {
            rsp_die_json(10006, '更新失败，'.$res['message']);
        }
        rsp_success_json();
    }
    
    public function lists($params = [])
    {
        log_message('---'.__METHOD__.'---'.json_encode($params));
        $params['project_id'] = $this->project_id;
        $res = $this->billing->post('/billingType/lists', $params);
        if ($res['code'] != 0) {
            rsp_die_json(10002, '查询失败，'.$res['message']);
        }
        $count = $this->billing->post('/billingType/count', $params);
        rsp_success_json(['total' => $count['content']['total'] ?? 0, 'lists' => $res['content']]);
    }
    
    public function show($params = [])
    {
        log_message('---'.__METHOD__.'---'.json_encode($params));
        if (!isTrueKey($params, 'billing_type_id')) {
            rsp_error_tips(10001, 'billing_type_id');
        }
        $params['project_id'] = $this->project_id;
        $res = $this->billing->post('/billingType/lists', $params);
        if ($res['code'] != 0) {
            rsp_die_json(10002, '查询失败，'.$res['message']);
        } elseif (empty($res['content'])) {
            rsp_success_json();
        }
        $billing_type_info = array_pop($res['content']);
        
        $employee_ids = array_unique(array_filter([
            $billing_type_info['created_by'],
            $billing_type_info['updated_by'],
        ]));
        if (!empty($employee_ids)) {
            $res = $this->user->post('/employee/lists', ['employee_ids' => $employee_ids]);
            $employee_info = $res['code'] == 0 ? many_array_column($res['content'], 'employee_id') : [];
        } else {
            $employee_info = [];
        }
        $billing_type_info['created_by_name'] = getArraysOfvalue($employee_info, $billing_type_info['created_by'],
            'full_name');
        $billing_type_info['updated_by_name'] = getArraysOfvalue($employee_info, $billing_type_info['updated_by'],
            'full_name');
        
        if ($billing_type_info['status_tag_id']) {
            $res = $this->tag->post('/tag/show', ['tag_id' => $billing_type_info['status_tag_id']]);
            $tag_info = $res['code'] == 0 ? $res['content'] : [];
        } else {
            $tag_info = [];
        }
        $billing_type_info['status_name'] = $tag_info['tag_name'] ?? '';
        
        $billing_type_info['created_at'] = $billing_type_info['created_at']
            ? date('Y-m-d H:i:s', $billing_type_info['created_at'])
            : '';
        $billing_type_info['updated_at'] = $billing_type_info['updated_at']
            ? date('Y-m-d H:i:s', $billing_type_info['updated_at'])
            : '';
        rsp_success_json($billing_type_info);
    }
    
    public function tree($params = [])
    {
        log_message('---'.__METHOD__.'---'.json_encode($params));
        $params['project_id'] = $this->project_id;
        $billing_type_info = $this->getBillingTypeInfo($params);
        if ($billing_type_info === false) {
            rsp_die_json(10002, '计费类型查询失败');
        } elseif (empty($billing_type_info)) {
            rsp_success_json();
        }
        
        $billing_type_ids = array_column($billing_type_info, 'id');
        $billing_account_info = $this->getBillingAccountInfo($billing_type_ids);
        if ($billing_account_info === false) {
            rsp_die_json(10002, '计费科目查询失败');
        }
        $billing_account_info = $this->recursion($billing_account_info);
        $billing_type_info = $this->recursion($billing_type_info, 0, $billing_account_info);
        rsp_success_json($billing_type_info);
    }
    
    private function getBillingTypeInfo($params)
    {
        $billing_type_info = $this->billing->post('/billingType/simpleLists', $params);
        if ($billing_type_info['code'] != 0) {
            log_message('---'.__METHOD__.'---计费类型查询失败,'.json_encode(['error' => $billing_type_info]));
            return false;
        } elseif (empty($billing_type_info['content'])) {
            return [];
        }
        return array_map(function ($m) {
            $res['id'] = $m['billing_type_id'];
            $res['pid'] = 0;
            $res['name'] = $m['billing_type_name'];
            $res['type'] = 'type';
            return $res;
        }, $billing_type_info['content']);
    }
    
    private function getBillingAccountInfo($billing_type_ids)
    {
        $billing_account_info = $this->billing->post('/billingAccount/simpleLists',
            ['billing_type_ids' => $billing_type_ids]);
        if ($billing_account_info['code'] != 0) {
            log_message('---'.__METHOD__.'---计费类型查询失败,'.json_encode(['error' => $billing_account_info]));
            return false;
        } elseif (empty($billing_account_info['content'])) {
            return [];
        }
        return array_map(function ($m) {
            $res['id'] = $m['billing_account_id'];
            $res['pid'] = $m['parent_billing_account_id'];
            $res['billing_type_id'] = $m['billing_type_id'];
            $res['name'] = $m['billing_account_name'];
            $res['type'] = 'account';
            return $res;
        }, $billing_account_info['content']);
    }
    
    private function recursion($data, $pid = 0, $billing_account_info = [])
    {
        $arr = [];
        if (empty($data) || !is_array($data)) {
            return $arr;
        }
        foreach ($data as $value) {
            if ($value['pid'] == $pid) {
                if ($value['type'] == 'account' && $value['pid'] != 0) {
                    unset($value['billing_type_id']);
                }
                $value['children'] = $this->recursion($data, $value['id'], $billing_account_info);
                if ($value['type'] == 'type' && $billing_account_info) {
                    foreach ($billing_account_info as $item) {
                        if ($item['billing_type_id'] == $value['id']) {
                            $item['pid'] = $item['billing_type_id'];
                            unset($item['billing_type_id']);
                            $value['children'][] = $item;
                        }
                    }
                }
                $arr[] = $value;
            }
        }
        return $arr;
    }
    
}