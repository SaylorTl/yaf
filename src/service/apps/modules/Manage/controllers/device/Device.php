<?php

use Project\SpaceModel;

class Device extends Base
{

    public function lists($params = [])
    {
        if (!isTrueKey($params, 'page', 'pagesize')) rsp_error_tips(10001, 'page pagesize');

        $where = [
            'project_id' => $this->project_id,
        ];
        if (isTrueKey($params, 'space_id')) $where['space_id'] = $params['space_id'];
        if (isTrueKey($params, 'creationtime_begin')) $where['creationtime_begin'] = $params['creationtime_begin'];
        if (isTrueKey($params, 'creationtime_end')) $where['creationtime_end'] = $params['creationtime_end'];
        if (isTrueKey($params, 'project_ids')) $where['project_ids'] = $params['project_ids'];
        $device_ids = $this->pm->post('/device/v2/ids', $where);
        $device_ids = ($device_ids['code'] === 0 && $device_ids['content']) ? $device_ids['content'] : [];
        if (!$device_ids) rsp_success_json(['total' => 0, 'lists' => []]);

        // lists
        $where = [
            'page' => $params['page'],
            'pagesize' => $params['pagesize'],
            'need_status' => 1,
        ];
        if ($device_ids) $where['device_ids'] = array_unique(array_filter(array_column($device_ids, 'device_id')));
        if (is_not_empty($params, 'device_name')) $where['device_name'] = $params['device_name'];
        if (isTrueKey($params, 'device_type_tag_id')) $where['device_type_tag_id'] = $params['device_type_tag_id'];
        if (isTrueKey($params, 'warranty_tag_id')) $where['warranty_tag_id'] = $params['warranty_tag_id'];
        if (isTrueKey($params, 'energy_tag_id')) $where['energy_tag_id'] = $params['energy_tag_id'];
        if (isTrueKey($params, 'status_tag_id')) $where['status_tag_id'] = $params['status_tag_id'];

        $lists = $this->device->post('/device/lists', $where);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        unset($where['page'], $where['pagesize']);
        $total = $this->device->post('/device/count', $where);
        if ($total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => 0, 'lists' => $lists['content']]);

        // 模板信息
        $device_templates = $this->device->post('/device/template/lists', ['device_template_ids' => array_unique(array_filter(array_column($lists['content'], 'device_template_id')))]);
        $device_templates = ($device_templates['code'] === 0 && $device_templates['content']) ? many_array_column($device_templates['content'], 'device_template_id') : [];

        // 项目相关信息
        $devices = $this->pm->post('/device/v2/lists', ['device_ids' => array_unique(array_filter(array_column($lists['content'], 'device_id')))]);
        $devices = ($devices['code'] === 0 && $devices['content']) ? many_array_column($devices['content'], 'device_id') : [];
        $manager_ids = array_unique(array_filter(array_column($devices, 'manager_id')));

        // project
        $projects = $this->pm->post('/project/lists', ['project_ids' => array_unique(array_filter(array_column($devices, 'project_id')))]);
        $projects = ($projects['code'] === 0 && $projects['content']) ? many_array_column($projects['content'], 'project_id') : [];

        // space
        $space_ids = array_unique(array_filter(array_column($devices, 'space_id')));
        $space_branches = $this->pm->post('/space/batchBranch', ['space_ids' => $space_ids]);
        $space_branches = $space_branches['content'] ?? [];

        // employee
        $employee_ids = array_unique(array_merge(array_filter(array_column($lists['content'], 'created_by')), array_filter(array_column($lists['content'], 'updated_by'))));
        $employee_ids = array_unique(array_merge($employee_ids, $manager_ids));
        $employees = $this->user->post('/employee/lists', ['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        // resource lites
        $resource_lites = $this->resource->post('/resource/id/lite', ['resource_ids' => array_unique(array_filter(array_column($lists['content'], 'device_id')))]);
        $resource_lites = ($resource_lites['code'] === 0 && $resource_lites['content']) ? many_array_column($resource_lites['content'], 'resource_id') : [];

        $data = array_map(function ($m) use ($employees, $devices, $device_templates, $space_branches, $resource_lites, $projects) {
            unset($m['device_vendor_detail'], $m['vendor_id']);
            // 萤石监控地址
            $m['ys_video'] = '';
            foreach ($m['device_params'] ?: [] as $item) {
                if (strpos($item['device_template_param_value'], 'ezopen://') === 0) {
                    $m['ys_video'] = $item['device_template_param_value'];
                    break;
                }
            }
            $m['status_tag_id'] = $this->changeStatus($m['ys_device_status'],$m['status_tag_id']);
            $m['project_id'] = getArraysOfvalue($devices, $m['device_id'], 'project_id');
            $m['space_id'] = getArraysOfvalue($devices, $m['device_id'], 'space_id');
            $m['transfer_unit'] = getArraysOfvalue($devices, $m['device_id'], 'transfer_unit');
            $m['transfer_time'] = getArraysOfvalue($devices, $m['device_id'], 'transfer_time');
            $m['creationtime'] = getArraysOfvalue($devices, $m['device_id'], 'creationtime');
            $m['modifytime'] = getArraysOfvalue($devices, $m['device_id'], 'modifytime');
            $m['created_by'] = getArraysOfvalue($devices, $m['device_id'], 'created_by');
            $m['updated_by'] = getArraysOfvalue($devices, $m['device_id'], 'updated_by');
            $m['manager_id'] = getArraysOfvalue($devices, $m['device_id'], 'manager_id');

            $branch_info = SpaceModel::parseBranch($space_branches[$m['space_id']] ?? []);
            $m['space_name_full'] = $branch_info['space_name_full'] ?? '';

            $m['created_by'] = getArraysOfvalue($employees, $m['created_by'], 'full_name');
            $m['updated_by'] = getArraysOfvalue($employees, $m['updated_by'], 'full_name');
            $m['manager'] = getArraysOfvalue($employees, $m['manager_id'], 'full_name');

            $m['device_template_ability_tag_ids'] = getArraysOfvalue($device_templates, $m['device_template_id'], 'device_ability_tag_ids');

            $m['resource_lite'] = getArraysOfvalue($resource_lites, $m['device_id'], 'resource_lite');
            $m['client_app_id'] = getArraysOfvalue($projects, $m['project_id'], 'client_app_id');
            return $m;
        }, $lists['content']);
        rsp_success_json(['total' => (int)$total['content'], 'lists' => $data]);
    }
    
    private function changeStatus($ys_status,$old_status)
    {
        if( is_null($ys_status) ){
            return $old_status;
        }
        $status_tag_id = $old_status;
        if( $ys_status == 0 ){
            $status_tag_id = 1141;
        }elseif ($ys_status == 1){
            $status_tag_id = 1140;
        }
        return $status_tag_id;
    }

    public function add($params = [])
    {
        $fields = [
            'device_type_tag_id', 'device_template_id',
            'device_name', 'device_extcode', 'device_brand', 'device_model',
        ];
        if ($diff_fields = get_empty_fields($fields, $params)) rsp_error_tips(10001, implode('、', $diff_fields));

        if (!$this->project_id) rsp_die_json(10003, '当前项目未选择');
        $device_id = resource_id_generator(self::RESOURCE_TYPES['device']);
        if (!$device_id) rsp_die_json(10003, '生成device_id失败');

        //设备附件信息
        if (isset($params['device_file_ids'])) {
            if (!is_array($params['device_file_ids'])) rsp_die_json(10003, '设备附加文件信息格式错误');
            if (count($params['device_file_ids']) > 10) rsp_die_json(10003, '设备附件最多只能上传10个');
        }
        // check device_params
        $fields = [
            'device_template_param_key',
        ];
        $device_params = isset($params['device_params']) ? (is_array($params['device_params']) ? $params['device_params'] : json_decode($params['device_params'], true)) : [];
        foreach ($device_params as $item) {
            if ($diff_fields = get_empty_fields($fields, $item)) rsp_error_tips(10001, 'device_params字段内部');
        }
        $device_template_param_key_names = array_column($device_params, 'device_template_param_key');
        if (count($device_template_param_key_names) !== count(array_unique($device_template_param_key_names))) {
            rsp_error_tips(10003, 'device_template_param_key');
        }

        // check device_ability_tag_ids
        $fields = [
            'device_ability_tag_id',
        ];
        $device_ability_tag_ids = isset($params['device_ability_tag_ids']) ? (is_array($params['device_ability_tag_ids']) ? $params['device_ability_tag_ids'] : json_decode($params['device_ability_tag_ids'], true)) : [];
        foreach ($device_ability_tag_ids as $item) {
            if ($diff_fields = get_empty_fields($fields, $item)) rsp_error_tips(10001, 'device_ability_tag_ids字段内部');
        }

        $space_id = $params['space_id'] ?? '';
        // check duplicate
        if ($space_id) {
            $possible_device_ids = $this->pm->post('/device/v2/lists', ['space_id' => $space_id]);
            $possible_device_ids = ($possible_device_ids['code'] === 0 && $possible_device_ids['content']) ? $possible_device_ids['content'] : [];
            if ($possible_device_ids) {
                $possible_device_ids = array_unique(array_filter(array_column($possible_device_ids, 'device_id')));
                $possible_devices = $this->device->post('/device/lists', ['device_ids' => $possible_device_ids]);
                $possible_devices = ($possible_devices['code'] === 0 && $possible_devices['content']) ? $possible_devices['content'] : [];
                if ($possible_devices) {
                    if (in_array($params['device_name'], array_column($possible_devices, 'device_name'))) rsp_error_tips(10003, '设备名称');
                }
            }
        }

        // device_vendor_detail
        $project = $this->pm->post('/project/projects', ['project_id' => $this->project_id]);
        $project = ($project['code'] === 0 && $project['content']) ? $project['content'][0] : [];

        $space_branch = [];
        if ($space_id) {
            $space_branch = $this->pm->post('/space/branch', ['space_id' => $space_id]);
            $space_branch = ($space_branch['code'] === 0 && $space_branch['content']) ? $space_branch['content'] : [];
        }

        $device_vendor_detail = [
            'deviceName' => $params['device_name'],
            'deviceSn' => $params['device_extcode'],
            'project' => $project['project_name'],
            'community' => '',
            'building' => '',
            'unit' => '',
            'floor' => '',
            'room' => '',
            'device_template_params' => [],
        ];
        foreach ($device_params ?: [] as $item) {
            $device_vendor_detail['device_template_params'][$item['device_template_param_key']] = $item['device_template_param_value'];
        }
        foreach ($space_branch ?: [] as $item) {
            if ($item['space_type'] === 244) {
                $device_vendor_detail['community'] = $item['space_name'];
                $device_vendor_detail['building'] = $item['space_name'];
                continue;
            }
            if ($item['space_type'] === 1392) {
                $device_vendor_detail['unit'] = $item['space_name'];
                continue;
            }
            if ($item['space_type'] === 1393) {
                $device_vendor_detail['floor'] = $item['space_name'];
                continue;
            }
            if ($item['space_type'] === 1394) {
                $device_vendor_detail['room'] = $item['space_name'];
            }
        }

        // add device
        $result = $this->device->post('/device/add', [
            'device_id' => $device_id,
            'device_type_tag_id' => $params['device_type_tag_id'],
            'device_template_id' => $params['device_template_id'],
            'device_name' => $params['device_name'],
            'device_extcode' => $params['device_extcode'],
            'device_brand' => $params['device_brand'],
            'device_model' => $params['device_model'],
            'device_power' => $params['device_power'] ?? '',
            'warranty_time_begin' => isTrueKey($params, 'warranty_time_begin') ? strtotime($params['warranty_time_begin']) : 0,
            'warranty_time_end' => isTrueKey($params, 'warranty_time_end') ? strtotime($params['warranty_time_end']) : 0,
            'energy_tag_id' => $params['energy_tag_id'] ?? 0,
            'remark' => $params['remark'] ?? '',
            'device_params' => $device_params,
            'device_ability_tag_ids' => $device_ability_tag_ids,
            'device_vendor_detail' => $device_vendor_detail,
            'device_file_ids' => isset($params['device_file_ids']) ? json_encode($params['device_file_ids']) : json_encode([]),
        ]);
        if ($result['code'] !== 0) rsp_die_json(10005, $result['message']);

        // binding to pm
        $result = $this->pm->post('/device/v2/add', [
            'device_id' => $device_id,
            'project_id' => $this->project_id,
            'space_id' => $space_id,
            'transfer_unit' => $params['transfer_unit'] ?? '',
            'transfer_time' => isTrueKey($params, 'transfer_time') ? strtotime($params['transfer_time']) : 0,
            'created_by' => $this->employee_id,
            'updated_by' => $this->employee_id,
            'manager_id' => $params['manager_id'] ?? '',
        ]);
        if ($result['code'] !== 0) rsp_die_json(10005, $result['message']);

        rsp_success_json($device_id);
    }

    public function update($params = [])
    {
        $fields = [
            'device_id',
            'device_type_tag_id', 'device_template_id',
            'device_name', 'device_extcode', 'device_brand', 'device_model',
        ];
        if ($diff_fields = get_empty_fields($fields, $params)) rsp_error_tips(10001, implode('、', $diff_fields));


        //设备附件信息
        if (isset($params['device_file_ids'])) {
            if (!is_array($params['device_file_ids'])) rsp_die_json(10003, '设备附加文件信息格式错误');
            if (count($params['device_file_ids']) > 10) rsp_die_json(10003, '设备附件最多只能上传10个');
        }

        // check device_params
        $fields = [
            'device_template_param_key',
        ];
        $device_params = isset($params['device_params']) ? (is_array($params['device_params']) ? $params['device_params'] : json_decode($params['device_params'], true)) : [];
        foreach ($device_params as $item) {
            if ($diff_fields = get_empty_fields($fields, $item)) rsp_error_tips(10001, 'device_params字段内部');
        }
        $device_template_param_key_names = array_column($device_params, 'device_template_param_key');
        if (count($device_template_param_key_names) !== count(array_unique($device_template_param_key_names))) {
            rsp_error_tips(10003, 'device_template_param_key');
        }

        // check device_ability_tag_ids
        $fields = [
            'device_ability_tag_id',
        ];
        $device_ability_tag_ids = isset($params['device_ability_tag_ids']) ? (is_array($params['device_ability_tag_ids']) ? $params['device_ability_tag_ids'] : json_decode($params['device_ability_tag_ids'], true)) : [];
        foreach ($device_ability_tag_ids as $item) {
            if ($diff_fields = get_empty_fields($fields, $item)) rsp_error_tips(10001, 'device_ability_tag_ids字段内部');
        }

        $space_id = $params['space_id'] ?? '';
        // check duplicate
        if ($space_id) {
            $possible_device_ids = $this->pm->post('/device/v2/lists', ['space_id' => $space_id]);
            $possible_device_ids = ($possible_device_ids['code'] === 0 && $possible_device_ids['content']) ? $possible_device_ids['content'] : [];
            if ($possible_device_ids) {
                $possible_device_ids = array_unique(array_filter(array_column($possible_device_ids, 'device_id')));
                $possible_devices = $this->device->post('/device/lists', ['device_ids' => $possible_device_ids]);
                $possible_devices = ($possible_devices['code'] === 0 && $possible_devices['content']) ? $possible_devices['content'] : [];
                foreach ($possible_devices ?: [] as $item) {
                    if ($item['device_name'] === $params['device_name']) {
                        if ($item['device_id'] !== $params['device_id']) rsp_error_tips(10003, '设备名称');
                    }
                }
            }

        }

        // device_vendor_detail
        $project = $this->pm->post('/project/projects', ['project_id' => $this->project_id]);
        $project = ($project['code'] === 0 && $project['content']) ? $project['content'][0] : [];

        $space_branch = [];
        if ($space_id) {
            $space_branch = $this->pm->post('/space/branch', ['space_id' => $space_id]);
            $space_branch = ($space_branch['code'] === 0 && $space_branch['content']) ? $space_branch['content'] : [];
        }

        $device_vendor_detail = [
            'deviceName' => $params['device_name'],
            'deviceSn' => $params['device_extcode'],
            'project' => $project['project_name'],
            'community' => '',
            'building' => '',
            'unit' => '',
            'floor' => '',
            'room' => '',
            'device_template_params' => [],
        ];
        foreach ($device_params ?: [] as $item) {
            $device_vendor_detail['device_template_params'][$item['device_template_param_key']] = $item['device_template_param_value'];
        }
        foreach ($space_branch ?: [] as $item) {
            if ($item['space_type'] === 244) {
                $device_vendor_detail['community'] = $item['space_name'];
                $device_vendor_detail['building'] = $item['space_name'];
                continue;
            }
            if ($item['space_type'] === 1392) {
                $device_vendor_detail['unit'] = $item['space_name'];
                continue;
            }
            if ($item['space_type'] === 1393) {
                $device_vendor_detail['floor'] = $item['space_name'];
                continue;
            }
            if ($item['space_type'] === 1394) {
                $device_vendor_detail['room'] = $item['space_name'];
            }
        }


        $result = $this->device->post('/device/update', [
            'device_id' => $params['device_id'],
            'device_type_tag_id' => $params['device_type_tag_id'],
            'device_template_id' => $params['device_template_id'],
            'device_name' => $params['device_name'],
            'device_extcode' => $params['device_extcode'],
            'device_brand' => $params['device_brand'],
            'device_model' => $params['device_model'],
            'device_power' => $params['device_power'] ?? '',
            'warranty_time_begin' => isTrueKey($params, 'warranty_time_begin') ? strtotime($params['warranty_time_begin']) : 0,
            'warranty_time_end' => isTrueKey($params, 'warranty_time_end') ? strtotime($params['warranty_time_end']) : 0,
            'energy_tag_id' => $params['energy_tag_id'] ?? 0,
            'remark' => $params['remark'] ?? '',
            'device_params' => $device_params,
            'device_ability_tag_ids' => $device_ability_tag_ids,
            'device_vendor_detail' => $device_vendor_detail,
            'device_file_ids' => isset($params['device_file_ids']) ? json_encode($params['device_file_ids']) : json_encode([]),
        ]);
        if ($result['code'] !== 0) rsp_die_json(10005, $result['message']);

        $result = $this->pm->post('/device/v2/update', [
            'device_id' => $params['device_id'],
            'project_id' => $this->project_id,
            'space_id' => $space_id,
            'transfer_unit' => $params['transfer_unit'] ?? '',
            'transfer_time' => isTrueKey($params, 'transfer_time') ? strtotime($params['transfer_time']) : 0,
            'updated_by' => $this->employee_id,
            'manager_id' => $params['manager_id'] ?? '',
        ]);
        if ($result['code'] !== 0) rsp_die_json(10005, $result['message']);

        rsp_success_json(1);
    }
}

