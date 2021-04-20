<?php

class BusinessConfig extends Base
{
    public function add($params = [])
    {
        log_message('---' . __METHOD__ . '---' . json_encode($params));
        if (!isTrueKey($params, 'billing_account_id')) {
            rsp_error_tips(10001, '计费科目信息');
        }
        if (!isTrueKey($params, 'billing_type_id')) {
            rsp_error_tips(10001, '计费类型');
        }
        if (!isTrueKey($params, 'status_tag_id')) {
            rsp_error_tips(10001, '状态标签信息');
        }
        if (isset($params['space_ids']) && !is_array($params['space_ids'])) {
            rsp_error_tips(10001, '关联空间信息');
        }
        if (!isTrueKey($params, 'rule_id')) {
            rsp_error_tips(10001, '规则');
        }
        if (isset($params['remark'])) {
            if (!is_string($params['remark'])) {
                rsp_die_json(10001, 'remark 参数类型错误');
            } elseif (mb_strlen($params['remark']) > 300) {
                rsp_die_json(10001, '备注不能超过300个字符');
            }
        }
        $params['business_config_id'] = resource_id_generator(self::RESOURCE_TYPES['business_config']);
        if (!$params['business_config_id']) {
            rsp_die_json(10005, '资源ID创建失败');
        }
        $params['created_by'] = $this->employee_id;
        $params['project_id'] = $this->project_id;
        //过滤空间
        $params['space_ids'] = $this->filterSpace($params['space_ids'] ?? []);
        //检查空间
        $this->checkSpace([
            'project_id' => $this->project_id,
            'billing_account_id' => $params['billing_account_id'],
            'billing_type_id' => $params['billing_type_id'],
            'status_tag_id' => $params['status_tag_id'],
            'need_space' => 1,
        ], $params['space_ids'] ?? []);
        $space_ids = $params['space_ids'];
        unset($params['space_ids']);
        $res = $this->billing->post('/businessConfig/add', $params);
        if ($res['code'] != 0) {
            rsp_die_json(10005, '添加失败，' . $res['message']);
        }
        if ($space_ids) {
            $space_ids = array_chunk($space_ids,400);
            foreach ($space_ids as $item){
                $res = $this->billing->post('/businessConfigSpace/bind', $params + ['space_ids' => $item]);
                if ($res['code'] != 0) {
                    rsp_die_json(10005, '空间关联失败，' . $res['message']);
                }
            }

        }
        rsp_success_json(['business_config_id' => $params['business_config_id']]);
    }

    public function update($params = [])
    {
        log_message('---' . __METHOD__ . '---' . json_encode($params));
        if (!isTrueKey($params, 'business_config_id')) {
            rsp_error_tips(10001, '业务配置ID');
        }
        if (isset($params['remark'])) {
            if (!is_string($params['remark'])) {
                rsp_die_json(10001, 'remark 参数类型错误');
            } elseif (mb_strlen($params['remark']) > 300) {
                rsp_die_json(10001, '备注不能超过300个字符');
            }
        }
        $res = $this->billing->post('/businessConfig/lists', [
            'business_config_id' => $params['business_config_id']
        ]);
        if ($res['code'] != 0) {
            rsp_die_json(10002, '业务配置检查失败');
        } elseif (empty($res['content'])) {
            rsp_die_json(10008, '未找到相关的业务配置');
        }
        $business_config_info = array_pop($res['content']);
        //过滤空间
        $params['space_ids'] = $this->filterSpace($params['space_ids'] ?? []);
        //检查空间
        $this->checkSpace([
            'project_id' => $this->project_id,
            'not_business_config_id' => $params['business_config_id'],
            'billing_account_id' => $params['billing_account_id'] ?? $business_config_info['billing_account_id'],
            'billing_type_id' => $params['billing_type_id'] ?? $business_config_info['billing_type_id'],
            'status_tag_id' => $params['status_tag_id'] ?? $business_config_info['status_tag_id'],
            'need_space' => 1,
        ], $params['space_ids'] ?? []);
        $params['updated_by'] = $this->employee_id;
        //启动计费配置时，检查科目状态
        if (
            isTrueKey($params, 'status_tag_id')
            && $params['status_tag_id'] != $business_config_info['status_tag_id']
            && $params['status_tag_id'] == 1381
        ) {
            $res = $this->billing->post('/billingAccount/simpleLists',
                ['billing_account_id' => $business_config_info['billing_account_id']]);
            if ($res['code'] != 0) {
                rsp_die_json(10002, '计费科目信息获取失败');
            } elseif (empty($res['content'])) {
                rsp_die_json(10008, '计费科目不存在');
            }
            $billing_account = array_pop($res['content']);
            if ($billing_account['status_tag_id'] == 1380) {
                rsp_die_json(90002, '请先在计费科目中启用「' . $billing_account['billing_account_name'] . '」计费科目');
            }
        }
        $space_ids = $params['space_ids'];
        unset($params['space_ids']);
        $res = $this->billing->post('/businessConfig/update', $params);
        if ($res['code'] != 0) {
            rsp_die_json(10006, '更新失败，' . $res['message']);
        }
        if (!is_null($space_ids)) {
            $space_ids = empty($space_ids) ? [""] : $space_ids;
            log_message('========test===========1'.json_encode([
                    'space_ids count' => count($space_ids)
                ]));
            $space_ids = array_chunk($space_ids,400);
            foreach ($space_ids as $item){ 
                $res = $this->billing->post('/businessConfigSpace/bind', $params + ['space_ids' => $item]);
                if ($res['code'] != 0) {
                    rsp_die_json(10005, '空间关联失败，' . $res['message']);
                }
                $this->carRuleSync($item, $params['rule_id'] ?? $business_config_info['rule_id']);
            }
        }
        rsp_success_json();
    }

    /**
     * 检查是否存在已启用规则的空间信息
     * @param $params
     * @param $input_space_ids
     * @return bool
     */
    private function checkSpace($params, $input_space_ids)
    {
        if (!is_array($input_space_ids) || !$input_space_ids) {
            return false;
        }
        if (isTrueKey($params, 'status_tag_id') && $params['status_tag_id'] != 1381) {
            return true;
        }
        $res = $this->billing->post('/businessConfig/lists', $params);
        if ($res['code'] != 0) {
            rsp_die_json(10002, '空间检查-信息查询失败，' . $res['message']);
        } elseif (empty($res['content'])) {
            return true;
        }
        $space_ids = array_column($res['content'], 'space_ids');
        $space_ids = array_unique(array_reduce($space_ids, 'array_merge', []));
        $use_space_ids = array_intersect($input_space_ids, $space_ids);
        if ($use_space_ids) {
            $use_space_ids = array_chunk($use_space_ids, 400); //分块处理，防止空间ID过多
            foreach ($use_space_ids as $item) {
                $space_info = $this->pm->post('/space/lists', ['space_ids' => $item, 'is_paging' => 'N']);
                $msg = '该类型的计费科目在';
                if (!empty($space_info['content'])) {
                    $space_info['content'] = array_map(function ($m) {
                        $m['space_name'] = '【' . $m['space_name'] . '】';
                        return $m;
                    }, $space_info['content']);
                    $msg .= (implode('、', array_column($space_info['content'], 'space_name')));
                }
                $msg .= '下已存在启用状态的计费规则！请选择其他应用对象！';
                rsp_die_json(90002, $msg);
            }
        }
        return true;
    }

    /**
     * 过滤不属于当前项目和存在子集的空间
     * @param $params
     * @param $input_space_ids
     * @return bool|array
     */
    private function filterSpace($input_space_ids)
    {
        if (!is_array($input_space_ids) || !$input_space_ids) {
            return [];
        }
        $input_space_ids = array_filter(array_unique($input_space_ids));
        $input_space_ids = array_chunk($input_space_ids, 400);//分块处理
        $result = [];
        foreach ($input_space_ids as $item) {
            //过滤非当前项目的空间ID
            $space_info = $this->pm->post('/space/lists', [
                'space_ids' => $item,
                'project_id' => $this->project_id,
                'is_paging' => 'N',
            ]);
            $item = empty($space_info['content']) ? [] : array_column($space_info['content'], 'space_id');
            if (empty($item)) {
                continue;
            }
            //过滤父级空间
            $space_info = $this->pm->post('/space/lists', [
                'parent_ids' => $item,
                'project_id' => $this->project_id,
                'is_paging' => 'N',
            ]);
            $parent_ids = empty($space_info['content']) ? [] : array_column($space_info['content'], 'parent_id');
            $parent_ids = array_unique($parent_ids);
            $temp_space_ids = array_diff($item, $parent_ids) ?: null;
            $result = is_null($temp_space_ids) ? $result : array_merge($result, $temp_space_ids);
        };
        return $input_space_ids && empty($result) ? null : $result;
    }

    public function lists($params = [])
    {
        log_message('---' . __METHOD__ . '---' . json_encode($params));
        if (isTrueKey($params, 'space_id')) {
            $res = $this->billing->post('/businessConfigSpace/lists', [
                'space_id' => $params['space_id'],
                'deleted' => 'N'
            ]);
            if ($res['code'] != 0) {
                rsp_die_json(10002, '查询失败，' . $res['message']);
            } elseif (empty($res['content'])) {
                rsp_success_json(['total' => 0, 'lists' => []]);
            }
            $params['business_config_ids'] = array_ufc($res['content'], 'business_config_id');
        }
        $params['project_id'] = $this->project_id;
        $res = $this->billing->post('/businessConfig/lists', $params);
        if ($res['code'] != 0) {
            rsp_die_json(10002, '查询失败，' . $res['message']);
        } elseif (empty($res['content'])) {
            rsp_success_json(['total' => 0, 'lists' => []]);
        }
        $business_config_info = $res['content'];
        unset($res['content']);
        $count = $this->billing->post('/businessConfig/count', $params);
        //计费类型
        $billing_type_ids = array_ufc($business_config_info, 'billing_type_id');
        if ($billing_type_ids) {
            $res = $this->billing->post('/billingType/simpleLists', ['billing_type_ids' => $billing_type_ids]);
            $billing_type_info = $res['code'] == 0 ? many_array_column($res['content'], 'billing_type_id') : [];
        } else {
            $billing_type_info = [];
        }
        //计费科目
        $billing_account_ids = array_ufc($business_config_info, 'billing_account_id');
        if ($billing_account_ids) {
            $res = $this->billing->post('/billingAccount/simpleLists', ['billing_account_ids' => $billing_account_ids]);
            $billing_account_info = $res['code'] == 0 ? many_array_column($res['content'], 'billing_account_id') : [];
        } else {
            $billing_account_info = [];
        }
        //状态
        $tag_ids = array_ufc($business_config_info, 'status_tag_id');
        $tag_ids = array_merge($tag_ids, array_ufc($billing_type_info, 'billing_type_tag_id'));
        $tag_ids = array_merge($tag_ids, array_ufc($billing_account_info, 'billing_account_tag_id'));
        if ($tag_ids) {
            $res = $this->tag->post('/tag/lists', ['tag_ids' => $tag_ids, 'nolevel' => 'Y']);
            $tag_info = $res['code'] == 0 ? many_array_column($res['content'], 'tag_id') : [];
        } else {
            $tag_info = [];
        }
        //计费规则
        $rule_ids = array_values(array_ufc($business_config_info, 'rule_id'));
        if ($rule_ids) {
            $res = $this->rule->post('/decision', json_encode(['_id' => $rule_ids]));
            $rule_info = $res['code'] == 0 ? many_array_column($res['content'], 'sid') : [];
        } else {
            $rule_info = [];
        }
        //创建人和修改人
        $employee_ids = array_ufc($business_config_info, 'created_by');
        $employee_ids = array_filter(array_merge($employee_ids, array_ufc($business_config_info, 'updated_by')));
        if (!empty($employee_ids)) {
            $res = $this->user->post('/employee/lists', ['employee_ids' => $employee_ids]);
            $employee_info = $res['code'] == 0 ? many_array_column($res['content'], 'employee_id') : [];
        } else {
            $employee_info = [];
        }

        $business_config_info = array_map(function ($m) use (
            $billing_type_info,
            $billing_account_info,
            $tag_info,
            $rule_info,
            $employee_info
        ) {
            $m['billing_type_name'] = getArraysOfvalue($billing_type_info, $m['billing_type_id'], 'billing_type_name');
            $m['billing_account_name'] = getArraysOfvalue($billing_account_info, $m['billing_account_id'],
                'billing_account_name');
            $m['billing_type_tag_id'] = (int)getArraysOfvalue($billing_type_info, $m['billing_type_id'],
                'billing_type_tag_id');
            $m['billing_account_tag_id'] = (int)getArraysOfvalue($billing_account_info, $m['billing_account_id'],
                'billing_account_tag_id');
            if (strlen($m['billing_type_name']) < 1 && $m['billing_type_tag_id']) {//
                $m['billing_type_name'] = getArraysOfvalue($tag_info, $m['billing_type_tag_id'], 'tag_name');
            }
            if (strlen($m['billing_account_name']) < 1 && $m['billing_account_tag_id']) {
                $m['billing_account_name'] = getArraysOfvalue($tag_info, $m['billing_account_tag_id'], 'tag_name');
            }
            $m['status_name'] = getArraysOfvalue($tag_info, $m['status_tag_id'], 'tag_name');
            $m['created_at'] = $m['created_at'] ? date('Y-m-d H:i:s', $m['created_at']) : '';
            $m['updated_at'] = $m['updated_at'] ? date('Y-m-d H:i:s', $m['updated_at']) : '';
            $m['rule_name'] = getArraysOfvalue($rule_info, $m['rule_id'], 'name');
            $m['rule_description'] = getArraysOfvalue($rule_info, $m['rule_id'], 'description');
            $m['created_by_name'] = getArraysOfvalue($employee_info, $m['created_by'], 'full_name');
            $m['updated_by_name'] = getArraysOfvalue($employee_info, $m['updated_by'], 'full_name');
            return $m;
        }, $business_config_info);
        rsp_success_json(['total' => $count['content']['total'] ?? 0, 'lists' => $business_config_info]);
    }

    public function show($params = [])
    {
        log_message('---' . __METHOD__ . '---' . json_encode($params));
        if (!isTrueKey($params, 'business_config_id')) {
            rsp_error_tips(10001, 'business_config_id');
        }
        $params['project_id'] = $this->project_id;
        $res = $this->billing->post('/businessConfig/lists', $params);
        if ($res['code'] != 0) {
            rsp_die_json(10002, '查询失败，' . $res['message']);
        } elseif (empty($res['content'])) {
            rsp_success_json();
        }
        $business_config_info = array_pop($res['content']);
        //关联的空间
        $res = $this->billing->post('/businessConfigSpace/lists', [
            'business_config_id' => $params['business_config_id'],
            'deleted' => 'N',
        ]);
        $business_config_info['space_ids'] = empty($res['content']) ? [] : array_column($res['content'], 'space_id');
        //操作人
        $employee_ids = array_unique(array_filter([
            $business_config_info['created_by'],
            $business_config_info['updated_by'],
        ]));
        if (!empty($employee_ids)) {
            $res = $this->user->post('/employee/lists', ['employee_ids' => $employee_ids]);
            $employee_info = $res['code'] == 0 ? many_array_column($res['content'], 'employee_id') : [];
        } else {
            $employee_info = [];
        }
        $business_config_info['created_by_name'] = getArraysOfvalue($employee_info, $business_config_info['created_by'],
            'full_name');
        $business_config_info['updated_by_name'] = getArraysOfvalue($employee_info, $business_config_info['updated_by'],
            'full_name');
        //状态
        $business_config_info['status_name'] = '';
        if ($business_config_info['status_tag_id']) {
            $res = $this->tag->post('/tag/show', ['tag_id' => $business_config_info['status_tag_id']]);
            $tag_info = ($res['code'] == 0 && !empty($res['content'])) ? $res['content'] : [];
            $business_config_info['status_name'] = $tag_info['tag_name'] ?? '';
        }

        //计费类型
        $business_config_info['billing_type_name'] = '';
        $billing_type_info = [];
        if ($business_config_info['billing_type_id']) {
            $res = $this->billing->post('/billingType/lists', [
                'billing_type_id' => $business_config_info['billing_type_id']
            ]);
            $billing_type_info = ($res['code'] == 0 && !empty($res['content'])) ? array_pop($res['content']) : [];
            $business_config_info['billing_type_tag_id'] = $billing_type_info['billing_type_tag_id'] ?? '';
            $business_config_info['billing_type_name'] = $billing_type_info['billing_type_name'] ?? '';
        }
        //计费科目
        $business_config_info['billing_account_name'] = '';
        $billing_account_info = [];
        if ($business_config_info['billing_account_id']) {
            $res = $this->billing->post('/billingAccount/lists', [
                'billing_account_id' => $business_config_info['billing_account_id']
            ]);
            $billing_account_info = ($res['code'] == 0 && !empty($res['content'])) ? array_pop($res['content']) : [];
            $business_config_info['billing_account_tag_id'] = $billing_account_info['billing_account_tag_id'] ?? '';
            $business_config_info['billing_account_name'] = $billing_account_info['billing_account_name'] ?? '';
        }
        //计费规则
        $business_config_info['rule_name'] = '';
        if ($business_config_info['rule_id']) {
            $res = $this->rule->post('/decision', json_encode(['_id' => [$business_config_info['rule_id']]]));
            $rule_info = ($res['code'] == 0 && !empty($res['content'])) ? array_pop($res['content']) : [];
            $business_config_info['rule_name'] = $rule_info['name'] ?? '';
        }
        $tag_ids = [
            $business_config_info['status_tag_id'] ?? 0,
            $billing_type_info['billing_type_tag_id'] ?? 0,
            $billing_account_info['billing_account_tag_id'] ?? 0,
        ];
        $tag_ids = array_unique(array_filter($tag_ids));
        $tag_info = [];
        if ($tag_ids) {
            $res = $this->tag->post('/tag/lists', ['tag_ids' => $tag_ids, 'nolevel' => 'Y']);
            $tag_info = $res['code'] == 0 ? many_array_column($res['content'], 'tag_id') : [];
        }
        $business_config_info['status_name'] = getArraysOfvalue(
            $tag_info,
            $business_config_info['status_tag_id'],
            'tag_name'
        );
        if (strlen($business_config_info['billing_type_name']) < 1 && $business_config_info['billing_type_tag_id']) {
            $business_config_info['billing_type_name'] = getArraysOfvalue(
                $tag_info,
                $business_config_info['billing_type_tag_id'],
                'tag_name'
            );
        }
        if (strlen($business_config_info['billing_account_name']) < 1 && $business_config_info['billing_account_tag_id']) {
            $business_config_info['billing_account_name'] = getArraysOfvalue(
                $tag_info,
                $business_config_info['billing_account_tag_id'],
                'tag_name'
            );
        }
        //项目
        $business_config_info['project_name'] = '';
        if ($business_config_info['project_id']) {
            $res = $this->pm->post('/project/show', ['project_id' => $business_config_info['project_id']]);
            $project_info = ($res['code'] == 0 && !empty($res['content'])) ? $res['content'] : [];
            $business_config_info['project_name'] = $project_info['project_name'] ?? '';
        }
        $business_config_info['created_at'] = $business_config_info['created_at']
            ? date('Y-m-d H:i:s', $business_config_info['created_at'])
            : '';
        $business_config_info['updated_at'] = $business_config_info['updated_at'] ?
            date('Y-m-d H:i:s', $business_config_info['updated_at'])
            : '';
        rsp_success_json($business_config_info);
    }


    public function getRule($params = [])
    {
        if (!isTrueKey($params, 'space_id', 'billing_account_tag_id', 'status_tag_id')) {
            rsp_die_json(10001, 'space_id、billing_account_tag_id 或 status_tag_id 参数缺失');
        }
        if (!$this->project_id) {
            rsp_die_json(10001, '项目信息缺失');
        }
        $params['project_id'] = $this->project_id;
        $res = $this->billing->post('/businessConfig/getRule', $params);
        if ($res['code'] != 0) {
            rsp_die_json(10002, '查询失败，' . $res['message']);
        }
        $data = $res['content'] ?? [];
        unset($res['content']);
        if ($data && isTrueKey($data, 'rule_id')) {
            $res = $this->rule->post('/decision', json_encode(['_id' => [$data['rule_id']]]));
            $rule_info = ($res['code'] == 0 && !empty($res['content'])) ? array_pop($res['content']) : [];
            $data['rule_name'] = $rule_info['name'] ?? '';
        }
        rsp_success_json($data);
    }

    private function carRuleSync(array $space_ids, string $rule_id)
    {
        $space_ids = array_unique(array_filter($space_ids));
        if (empty($space_ids) || !$rule_id) {
            return false;
        }
        $res = $this->rule->post('/decision', json_encode(['_id' => [$rule_id]]));
        $rule_info = ($res['code'] == 0 && !empty($res['content'])) ? array_pop($res['content']) : [];
        if (empty($rule_info)) {
            return false;
        }
        foreach ($space_ids as $space_id) {
            Comm_EventTrigger::push('car_rule_sync', [
                'space_id' => $space_id,
                'rule_name' => $rule_info['name'] ?? ''
            ]);
        }
        return true;
    }

}