<?php

class Devicetemplate extends Base {

	public function lists($params = [])
    {
        if (!isTrueKey($params, 'page', 'pagesize')) rsp_error_tips(10001, 'page pagesize');

        $lists = $this->device->post('/device/template/lists', $params);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        unset($params['page'], $params['pagesize']);
        $total = $this->device->post('/device/template/count', $params);
        if ($total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => 0, 'lists' => $lists['content']]);

        // 供应商
        $vendor_ids = array_unique(array_filter(array_column($lists['content'], 'vendor_id')));
        $vendors = $this->device->post('/vendor/lists', ['vendor_ids' => $vendor_ids]);
        $vendors = ($vendors['code'] === 0 && $vendors['content']) ? many_array_column($vendors['content'], 'vendor_id') : [];

        // 员工
        $employee_ids = array_unique(array_merge(array_filter(array_column($lists['content'], 'created_by')), array_filter(array_column($lists['content'], 'updated_by'))));
        $employees = $this->user->post('/employee/lists',['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        $data = array_map(function($m)use($employees, $vendors){
            $m['vendor_name'] = getArraysOfvalue($vendors, $m['vendor_id'], 'vendor_name');
            $m['created_by'] = getArraysOfvalue($employees, $m['created_by'], 'full_name');
            $m['updated_by'] = getArraysOfvalue($employees, $m['updated_by'], 'full_name');
            return $m;
        },$lists['content']);
        rsp_success_json(['total' => (int)$total['content'], 'lists' => $data]);
    }

    public function show($params = [])
    {
        if (!isTrueKey($params, ...['device_template_id'])) rsp_error_tips(10001);
        $lists = $this->device->post('/device/template/lists', [
            'device_template_id' => $params['device_template_id'],
            'page' => 1,
            'pagesize' => 1,
        ]);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json([]);
        rsp_success_json($lists['content'][0]);
    }

    public function add($params = [])
    {
        $fields = [
            'device_type_tag_id', 'vendor_id', 'device_template_name',
        ];
        if ($diff_fields = get_empty_fields($fields, $params)) rsp_error_tips(10001, implode('、', $diff_fields));

        // device_template_param_keys
        if (isTrueKey($params, 'device_template_param_keys')) {
            $fields = [
                'device_template_param_key', 'device_template_param_key_cname', 'sort',
            ];
            $device_template_param_keys = is_array($params['device_template_param_keys']) ? $params['device_template_param_keys'] : json_decode($params['device_template_param_keys'], true);
            foreach ($device_template_param_keys as $item){
                if ($diff_fields = get_empty_fields($fields, $item)) rsp_error_tips(10001, 'device_template_param_keys字段内部');
            }
            $device_template_param_key_names = array_column($device_template_param_keys, 'device_template_param_key');
            if (count($device_template_param_key_names) !== count(array_unique($device_template_param_key_names))) {
                rsp_error_tips(10003, 'device_template_param_key');
            }
            $device_template_param_key_cnames = array_column($device_template_param_keys, 'device_template_param_key_cname');
            if (count($device_template_param_key_cnames) !== count(array_unique($device_template_param_key_cnames))) {
                rsp_error_tips(10003, 'device_template_param_key');
            }
            $params['device_template_param_keys'] = $device_template_param_keys;
        }

        // device_ability_tag_ids
        if (isTrueKey($params, 'device_ability_tag_ids')) {
            $fields = [
                'device_ability_tag_id',
            ];
            $device_ability_tag_ids = is_array($params['device_ability_tag_ids']) ? $params['device_ability_tag_ids'] : json_decode($params['device_ability_tag_ids'], true);
            foreach ($device_ability_tag_ids as $item){
                if ($diff_fields = get_empty_fields($fields, $item)) rsp_error_tips(10001, 'device_ability_tag_ids字段内部');
            }
            $params['device_ability_tag_ids'] = $device_ability_tag_ids;
        }

        $params['device_template_id'] = resource_id_generator(self::RESOURCE_TYPES['device_template']);
        if (!$params['device_template_id']) rsp_die_json(10001, '生成device_template_id失败');

        $params['created_by'] = $params['updated_by'] = $this->employee_id;

        $result = $this->device->post('/device/template/add', $params);
        if ($result['code'] !== 0) rsp_die_json(10005, $result['message']);
        //添加审计日志
        Comm_AuditLogs::push(
            1328,
            $result['content'],
            '添加设备模板',
            1323,
            $params,
            '成功'
        );
        rsp_success_json($result['content']);
    }

    public function update($params = [])
    {
        $fields = [
            'device_template_id', 'device_type_tag_id', 'vendor_id', 'device_template_name',
        ];
        if ($diff_fields = get_empty_fields($fields, $params)) rsp_error_tips(10001, implode('、', $diff_fields));

        // device_template_param_keys
        if (isTrueKey($params, 'device_template_param_keys')) {
            $fields = [
                'device_template_param_key', 'device_template_param_key_cname', 'sort',
            ];
            $device_template_param_keys = is_array($params['device_template_param_keys']) ? $params['device_template_param_keys'] : json_decode($params['device_template_param_keys'], true);
            foreach ($device_template_param_keys as $item){
                if ($diff_fields = get_empty_fields($fields, $item)) rsp_error_tips(10001, 'device_template_param_keys字段内部');
            }
            $device_template_param_key_names = array_column($device_template_param_keys, 'device_template_param_key');
            if (count($device_template_param_key_names) !== count(array_unique($device_template_param_key_names))) {
                rsp_error_tips(10003, 'device_template_param_key');
            }
            $device_template_param_key_cnames = array_column($device_template_param_keys, 'device_template_param_key_cname');
            if (count($device_template_param_key_cnames) !== count(array_unique($device_template_param_key_cnames))) {
                rsp_error_tips(10003, 'device_template_param_key');
            }
            $params['device_template_param_keys'] = $device_template_param_keys;
        }

        // device_ability_tag_ids
        if (isTrueKey($params, 'device_ability_tag_ids')) {
            $fields = [
                'device_ability_tag_id',
            ];
            $device_ability_tag_ids = is_array($params['device_ability_tag_ids']) ? $params['device_ability_tag_ids'] : json_decode($params['device_ability_tag_ids'], true);
            foreach ($device_ability_tag_ids as $item){
                if ($diff_fields = get_empty_fields($fields, $item)) rsp_error_tips(10001, 'device_ability_tag_ids字段内部');
            }
            $params['device_ability_tag_ids'] = $device_ability_tag_ids;
        }

        $params['updated_by'] = $this->employee_id;

        $result = $this->device->post('/device/template/update', $params);
        if ($result['code'] !== 0) rsp_die_json(10005, $result['message']);
        //添加审计日志
        Comm_AuditLogs::push(
            1328,
            $result['content'],
            '更新设备模板',
            1323,
            $params,
            $result['code'] == 0 ? '成功' : '失败'
        );
        rsp_success_json($result['content']);
    }
}

