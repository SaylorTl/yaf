<?php

use Project\SpaceModel;

class Device extends Base {

	public function show($params = [])
    {
        if (!isTrueKey($params, 'device_id') && !isTrueKey($params, 'resource_lite')) rsp_die_json(10001, 'device_id 或 resource_lite参数缺失或错误');
        if (isTrueKey($params, 'resource_lite')) {
            $resource_id = $this->resource->post('/resource/id/lite',['resource_lite' => $params['resource_lite']]);
            $resource_id = $resource_id['content'] ?? '';
            if (!$resource_id) rsp_die_json(10001, 'resource_lite参数错误');
            $params['device_id'] = $resource_id;
        }
        $where = [
            'page' => 1,
            'pagesize' => 1,
            'device_id' => $params['device_id'],
        ];
        $lists = $this->device->post('/device/lists', $where);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json([]);

        // 模板信息
        $device_templates = $this->device->post('/device/template/lists', ['device_template_ids' => array_unique(array_filter(array_column($lists['content'], 'device_template_id')))]);
        $device_templates = ($device_templates['code'] === 0 && $device_templates['content']) ? many_array_column($device_templates['content'], 'device_template_id') : [];

        // 项目相关信息
        $devices = $this->pm->post('/device/v2/lists', ['device_ids' => array_unique(array_filter(array_column($lists['content'], 'device_id')))]);
        $devices = ($devices['code'] === 0 && $devices['content']) ? many_array_column($devices['content'], 'device_id') : [];

        // project
        $projects = $this->pm->post('/project/projects',['project_ids' => array_unique(array_filter(array_column($devices, 'project_id')))]);
        $projects = ($projects['code'] === 0 && $projects['content']) ? many_array_column($projects['content'], 'project_id') : [];

        // space
        $spaces = $this->pm->post('/space/lists',['space_ids' => array_unique(array_filter(array_column($devices, 'space_id')))]);
        $spaces = ($spaces['code'] === 0 && $spaces['content']) ? many_array_column($spaces['content'], 'space_id') : [];

        // employee
        $employee_ids = array_unique(array_merge(array_filter(array_column($lists['content'], 'created_by')), array_filter(array_column($lists['content'], 'updated_by'))));
        $employees = $this->user->post('/employee/lists',['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        // resource lites
        $resource_lites = $this->resource->post('/resource/id/lite',['resource_ids' => array_unique(array_filter(array_column($lists['content'], 'device_id')))]);
        $resource_lites = ($resource_lites['code'] === 0 && $resource_lites['content']) ? many_array_column($resource_lites['content'], 'resource_id') : [];

        $data = array_map(function ($m) use ($employees, $devices, $device_templates, $spaces, $resource_lites, $projects) {
            unset($m['device_vendor_detail']);
            // 萤石监控地址
            $m['ys_video'] = '';
            foreach ($m['device_params'] ?: [] as $item) {
                if (strpos($item['device_template_param_value'], 'ezopen://') === 0) {
                    $m['ys_video'] = $item['device_template_param_value'];
                    break;
                }
            }
            $m['project_id'] = getArraysOfvalue($devices, $m['device_id'], 'project_id');
            $m['space_id'] = getArraysOfvalue($devices, $m['device_id'], 'space_id');
            $m['transfer_unit'] = getArraysOfvalue($devices, $m['device_id'], 'transfer_unit');
            $m['transfer_time'] = getArraysOfvalue($devices, $m['device_id'], 'transfer_time');
            $m['creationtime'] = getArraysOfvalue($devices, $m['device_id'], 'creationtime');
            $m['modifytime'] = getArraysOfvalue($devices, $m['device_id'], 'modifytime');
            $m['created_by'] = getArraysOfvalue($devices, $m['device_id'], 'created_by');
            $m['updated_by'] = getArraysOfvalue($devices, $m['device_id'], 'updated_by');

            $m['project_name'] = getArraysOfvalue($projects, $m['project_id'], 'project_name');
            $m['space_name'] = getArraysOfvalue($spaces, $m['space_id'], 'space_name');

            $m['created_by'] = getArraysOfvalue($employees, $m['created_by'], 'full_name');
            $m['updated_by'] = getArraysOfvalue($employees, $m['updated_by'], 'full_name');

            $m['vendor_id'] = getArraysOfvalue($device_templates, $m['device_template_id'], 'vendor_id');
            $m['device_template_ability_tag_ids'] = getArraysOfvalue($device_templates, $m['device_template_id'], 'device_ability_tag_ids');
            $m['device_template_type_tag_id'] = getArraysOfvalue($device_templates, $m['device_template_id'], 'device_template_type_tag_id');
            $m['resource_lite'] = getArraysOfvalue($resource_lites, $m['device_id'], 'resource_lite');

            $branch = $this->pm->post('/space/branch', ['space_id' => $m['space_id']]);
            $branch = ($branch['code'] === 0 && $branch['content']) ? $branch['content'] : [];
            $branch_info = SpaceModel::parseBranch($branch, '-');
            $m = array_merge($m, $branch_info);

            return $m;
        },$lists['content']);
        rsp_success_json($data[0] ?? []);
    }

}

