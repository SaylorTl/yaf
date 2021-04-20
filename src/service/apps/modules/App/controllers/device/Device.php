<?php

class Device extends Base
{
    /**
     * 根据mac地址查询设备ID
     * @param  array  $params
     * @return string
     */
    public function getDeviceIds($params = [])
    {
        $query = [];
        if (isTrueKey($params, 'mac')) {
            $query['device_template_param_value'] = $params['mac'];
        } elseif (isTrueKey($params, 'macs') && is_array($params['macs'])) {
            $query['device_template_param_values'] = array_unique(array_filter($params['macs']));
        }
        if (empty($query)) {
            rsp_die_json(10001, 'mac 或 macs 参数缺失或者格式错误');
        }
        $query['device_template_param_key'] = 'mac';
        $result = $this->device->post('/device/param/lists', $query);
        if ($result['code'] !== 0) {
            rsp_die_json(10002, '设备信息查询失败');
        }
        $data = [];
        if ($result['content'] && is_array($result['content'])) {
            foreach ($result['content'] as $item) {
                $data[] = [
                    'mac' => $item['device_template_param_value'],
                    'device_id' => $item['device_id']
                ];
            }
        }
        rsp_success_json($data);
    }
    
    /**
     * 设备添加接口,包含设备与项目的关联，可选的设备上报记录添加
     * @param  array  $params
     */
    public function add($params = [])
    {
        if (!$this->existsMemberId()) {
            rsp_error_tips(90002, '当前登录用户不是管理员');
        }
        $fields = [
            'project_id',
            'device_type_tag_id',
            'device_template_id',
            'device_name',
            'device_extcode',
            'device_brand',
            'device_model',
        ];
        if ($diff_fields = get_empty_fields($fields, $params)) {
            rsp_error_tips(10001, implode('、', $diff_fields));
        }
        $device_id = resource_id_generator(self::RESOURCE_TYPES['device']);
        if (!$device_id) {
            rsp_die_json(10007, '设备ID生成失败');
        }
        
        // check device_params
        $fields = [
            'device_template_param_key',
        ];
        $device_params = $this->getJsonStringParams($params, 'device_params');
        foreach ($device_params as $item) {
            if ($diff_fields = get_empty_fields($fields, $item)) {
                rsp_error_tips(10001, 'device_params字段内部数据缺失');
            }
        }
        $device_template_param_key_names = array_column($device_params, 'device_template_param_key');
        if (count($device_template_param_key_names) !== count(array_unique($device_template_param_key_names))) {
            rsp_error_tips(10003, 'device_template_param_key 存在重复项');
        }
        
        // check device_ability_tag_ids
        $fields = [
            'device_ability_tag_id',
        ];
        $device_ability_tag_ids = $this->getJsonStringParams($params, 'device_ability_tag_ids');
        foreach ($device_ability_tag_ids as $item) {
            if ($diff_fields = get_empty_fields($fields, $item)) {
                rsp_error_tips(10001, 'device_ability_tag_ids字段内部数据缺失');
            }
        }
        $space_id = $params['space_id'] ?? '';
        // 检查设备名称是否重复
        if ($space_id) {
            $duplicate = $this->checkForDuplicateDeviceName($space_id, $params['device_name']);
            if ($duplicate) {
                rsp_error_tips(10003, '该设备名称已存在，请调整后提交');
            }
        }
        // device_vendor_detail
        $project = $this->pm->post('/project/projects', ['project_id' => $params['project_id']]);
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
        foreach ($device_params as $item) {
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
        // 添加设备
        $error_msg = $this->addDevice(
            $device_id,
            $params,
            $device_params,
            $device_ability_tag_ids,
            $device_vendor_detail
        );
        if ($error_msg) {
            rsp_die_json(10005, '设备添加失败,'.$error_msg);
        }
        // 关联项目
        $error_msg = $this->bindingPm($device_id, $params['project_id'], $space_id, $params);
        if ($error_msg) {
            rsp_die_json(10005, '设备添加成功,但关联项目失败,'.$error_msg);
        }
        //添加上报记录
        if (isTrueKey($params, 'report')) {
            $error_msg = $this->addReportData($params, $params['project_id'], $device_id, $space_id, $device_params);
//            if ($error_msg) {
//                rsp_die_json(10005, '设备已添加，但设备上报记录添加失败，'.$error_msg);
//            }
        }
        rsp_success_json($device_id);
    }
    
    /**
     * 判断是否是管理员账户
     * @return bool|mixed
     */
    private function existsMemberId()
    {
        return $_SESSION['member_id'] ?? false;
    }
    
    /**
     * 检查项目权限
     * @param $project_id
     * @return bool|null
     */
    private function getProjectPermissions($project_id)
    {
        $result = $this->user->post('/tenement/lists', [
            'mobile' => $_SESSION['member_mobile'],
            'project_id' => $project_id,
        ]);
        if (!isset($result['code']) || $result['code'] != 0) {
            $permit = false;
        } elseif (empty($result['content'])) {
            $permit = null;
        } else {
            $permit = true;
        }
        return $permit;
    }
    
    /**
     * 获取json字符串参数，兼容json对象参数
     * @param $params
     * @param $key
     * @return array|mixed
     */
    private function getJsonStringParams($params, $key)
    {
        $data = [];
        if (isset($params[$key])) {
            $data = is_array($params[$key]) ? $params[$key] : json_decode($params[$key], true);
        }
        return $data;
    }
    
    /**
     * 检查设备名称是否重复
     * @param $space_id
     * @param $device_name
     * @return bool
     */
    private function checkForDuplicateDeviceName($space_id, $device_name)
    {
        $possible_device_ids = $this->pm->post('/device/v2/lists', ['space_id' => $space_id]);
        $possible_device_ids = ($possible_device_ids['code'] === 0 && $possible_device_ids['content']) ? $possible_device_ids['content'] : [];
        if ($possible_device_ids) {
            $possible_device_ids = array_unique(array_filter(array_column($possible_device_ids, 'device_id')));
            $possible_devices = $this->device->post('/device/lists', ['device_ids' => $possible_device_ids]);
            $possible_devices = ($possible_devices['code'] === 0 && $possible_devices['content']) ? $possible_devices['content'] : [];
            if ($possible_devices && in_array($device_name, array_column($possible_devices, 'device_name'))) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 添加设备
     * @param $device_id
     * @param $params
     * @param $device_params
     * @param $device_ability_tag_ids
     * @param $device_vendor_detail
     * @return string
     */
    private function addDevice($device_id, $params, $device_params, $device_ability_tag_ids, $device_vendor_detail)
    {
        // 添加设备
        $device_data = [
            'device_id' => $device_id,
            'device_type_tag_id' => $params['device_type_tag_id'],
            'device_template_id' => $params['device_template_id'],
            'device_name' => $params['device_name'],
            'device_extcode' => $params['device_extcode'],
            'device_brand' => $params['device_brand'],
            'device_model' => $params['device_model'],
            'device_power' => $params['device_power'] ?? '',
            'warranty_time_begin' => isTrueKey($params,
                'warranty_time_begin') ? strtotime($params['warranty_time_begin']) : 0,
            'warranty_time_end' => isTrueKey($params,
                'warranty_time_end') ? strtotime($params['warranty_time_end']) : 0,
            'energy_tag_id' => $params['energy_tag_id'] ?? 0,
            'remark' => $params['remark'] ?? '',
            'device_params' => $device_params,
            'device_ability_tag_ids' => $device_ability_tag_ids,
            'device_vendor_detail' => $device_vendor_detail,
        ];
        $result = $this->device->post('/device/add', $device_data);
        if ($result['code'] !== 0) {
            set_log(['device_add_res' => $result]);
            log_message('---APP/Device'.__FUNCTION__.'---'.json_encode([
                    'msg' => '设备添加失败',
                    'result' => $result
                ]));
            return $result['message'] ?? '设备添加服务异常';
        }
        return '';
    }
    
    /**
     * 绑定项目
     * @param $device_id
     * @param $project_id
     * @param $space_id
     * @param $params
     * @return string
     */
    private function bindingPm($device_id, $project_id, $space_id, $params)
    {
        $pm_device_data = [
            'device_id' => $device_id,
            'project_id' => $project_id,
            'space_id' => $space_id,
            'transfer_unit' => $params['transfer_unit'] ?? '',
            'transfer_time' => isTrueKey($params, 'transfer_time') ? strtotime($params['transfer_time']) : 0,
            'created_by' => $this->employee_id,
            'updated_by' => $this->employee_id,
        ];
        $result = $this->pm->post('/device/v2/add', $pm_device_data);
        if ($result['code'] !== 0) {
            set_log(['binding_pm_res' => $result]);
            log_message('---APP/Device'.__FUNCTION__.'---'.json_encode([
                    'msg' => '设备关联项目失败',
                    'result' => $result
                ]));
            return $result['message'] ?? '设备关联项目服务异常';
        }
        return '';
    }
    
    /**
     * 设备上报记录添加
     * @param $params
     * @param $device_id
     * @param $project_id
     * @param $space_id
     * @param $device_params
     * @return string
     */
    private function addReportData($params, $project_id, $device_id, $space_id, $device_params)
    {
        $device_report_data = [
            'user_id' => 1,
            'user_ext_tag_id' => 1195,
            'detail' => json_encode([
                'project_id' => $project_id,
                'device_template_id' => $params['device_template_id'],
                'device_type_tag_id' => $params['device_type_tag_id'],
                'device_name' => $params['device_name'],
                'device_brand' => $params['device_brand'],
                'device_model' => $params['device_model'],
                'device_power' => $params['device_power'] ?? '',
                'device_id' => $device_id,
                'space_id' => $space_id,
                'device_params' => $device_params,
            ], JSON_UNESCAPED_UNICODE),
        ];
        $result = $this->user->post('/userext/add', $device_report_data);
        if ($result['code'] !== 0) {
            set_log(['userext_add_res' => $result]);
            log_message('---APP/Device'.__FUNCTION__.'---'.json_encode([
                    'msg' => '设备上报记录添加失败',
                    'result' => $result
                ]));
            return $result['message'] ?? '设备上报记录服务异常';
        }
        return '';
    }
}

