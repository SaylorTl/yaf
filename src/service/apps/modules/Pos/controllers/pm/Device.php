<?php

class Device extends Base {

    public function lists($params = []){
        if (!isTrueKey($params, 'page', 'pagesize')) rsp_error_tips(10001, 'page pagesize');

        $device_ids = $where = [];
        if (isTrueKey($params, 'space_id')) $where['space_id'] = $params['space_id'];
        if (isTrueKey($params, 'space_id')) {
            $space_ids = $this->pm->post('/space/children', ['space_id' => $params['space_id']]);
            $space_ids = ($space_ids['code'] === 0 && $space_ids['content']) ? $space_ids['content'] : [];
            if (!$space_ids) rsp_success_json(['total' => 0, 'lists' => []]);
            unset($params['space_id']);
            $where['space_ids'] = array_unique(array_filter(array_column($space_ids, 'space_id')));
        }
        if (isTrueKey($params, 'creationtime_begin')) $where['creationtime_begin'] = $params['creationtime_begin'];
        if (isTrueKey($params, 'creationtime_end')) $where['creationtime_end'] = $params['creationtime_end'];
        if (isTrueKey($params, 'project_ids')) $where['project_ids'] = $params['project_ids'];
        if ($where) {
            $device_ids = $this->pm->post('/device/v2/ids', $where);
            $device_ids = ($device_ids['code'] === 0 && $device_ids['content']) ? $device_ids['content'] : [];
            if (!$device_ids) rsp_success_json(['total' => 0, 'lists' => []]);
        }

        // lists
        $where = [
            'page' => $params['page'],
            'pagesize' => $params['pagesize'],
        ];
        if ($device_ids) $where['device_ids'] = array_unique(array_filter(array_column($device_ids, 'device_id')));
        if (is_not_empty($params, 'device_name')) $where['device_name'] = $params['device_name'];
        if (is_not_empty($params, 'device_extcode')) $where['device_extcode'] = $params['device_extcode'];
        if (isTrueKey($params, 'device_type_tag_id')) $where['device_type_tag_id'] = $params['device_type_tag_id'];
        if (isTrueKey($params, 'warranty_tag_id')) $where['warranty_tag_id'] = $params['warranty_tag_id'];
        if (isTrueKey($params, 'energy_tag_id')) $where['energy_tag_id'] = $params['energy_tag_id'];

        $lists = $this->device->post('/device/lists', $where);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        unset($where['page'], $where['pagesize']);
        $total = $this->device->post('/device/count', $where);
        if ($total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => 0, 'lists' => $lists['content']]);

        // 项目相关信息
        $devices = $this->pm->post('/device/v2/lists', ['device_ids' => array_unique(array_filter(array_column($lists['content'], 'device_id')))]);
        $devices = ($devices['code'] === 0 && $devices['content']) ? many_array_column($devices['content'], 'device_id') : [];

        $projects = $this->pm->post('/project/projects', ['project_ids' => array_unique(array_filter(array_column($devices, 'project_id')))]);
        $projects = ($projects['code'] === 0 && $projects['content']) ? many_array_column($projects['content'], 'project_id') : [];

        // space
        $spaces = $this->pm->post('/space/lists',['space_ids' => array_unique(array_filter(array_column($devices, 'space_id')))]);
        $spaces = ($spaces['code'] === 0 && $spaces['content']) ? many_array_column($spaces['content'], 'space_id') : [];

        // employee
        $employee_ids = array_unique(array_merge(array_filter(array_column($lists['content'], 'created_by')), array_filter(array_column($lists['content'], 'updated_by'))));
        $employees = $this->user->post('/employee/lists',['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        $data = array_map(function($m)use($employees, $devices, $spaces, $projects){
            $m['device_vendor_detail'] = $m['device_vendor_detail'] ? json_decode($m['device_vendor_detail'], true) : [];
            $m['ys_video'] = isTrueKey($m['device_vendor_detail'], 'ys_video') ? $m['device_vendor_detail']['ys_video'] : '';
            unset($m['device_vendor_detail'], $m['vendor_id']);
            $m['project_id'] = getArraysOfvalue($devices, $m['device_id'], 'project_id');
            $m['project_name'] = getArraysOfvalue($projects, $m['project_id'], 'project_name');
            $m['space_id'] = getArraysOfvalue($devices, $m['device_id'], 'space_id');
            $m['transfer_unit'] = getArraysOfvalue($devices, $m['device_id'], 'transfer_unit');
            $m['transfer_time'] = getArraysOfvalue($devices, $m['device_id'], 'transfer_time');
            $m['creationtime'] = getArraysOfvalue($devices, $m['device_id'], 'creationtime');
            $m['modifytime'] = getArraysOfvalue($devices, $m['device_id'], 'modifytime');
            $m['created_by'] = getArraysOfvalue($devices, $m['device_id'], 'created_by');
            $m['updated_by'] = getArraysOfvalue($devices, $m['device_id'], 'updated_by');

            $m['space_name'] = getArraysOfvalue($spaces, $m['space_id'], 'space_name');

            $m['created_by'] = getArraysOfvalue($employees, $m['created_by'], 'full_name');
            $m['updated_by'] = getArraysOfvalue($employees, $m['updated_by'], 'full_name');
            return $m;
        },$lists['content']);
        rsp_success_json(['total' => (int)$total['content'], 'lists' => $data]);
    }

    public function add($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	$add_params = $this->params_handel($params);
    	$device_id = resource_id_generator(self::RESOURCE_TYPES['device']);
        if(!$device_id) rsp_die_json(10003,'添加失败');
        
        $add_params['device_id'] = $device_id;
        $add_params['creator'] =  $add_params['editor'] = $this->employee_id;
    	$result = $this->pm->post('/device/add', $add_params);
    	if($result['code'] != 0) rsp_die_json(10004,$result['message']);
    	rsp_success_json($result['content']);
    }

    public function update($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	if(isTrueKey($params,'device_id')  === false) rsp_die_json(10001,'device_id 参数缺失');
    	$update_params = $this->params_handel($params);
    	$update_params['device_id'] = $params['device_id'];

        $update_params['editor'] = $this->employee_id;
    	$result = $this->pm->post('/device/update', $update_params);
    	if($result['code'] != 0) rsp_die_json(10004,$result['message']);
    	rsp_success_json($result['content']);
    }

    public function params_handel($params){
    	$must_params = ['device_name','device_extcode','project_id','device_brand','device_model','device_tag_id','riding_position'];
    	if( $empty_fileds = get_empty_fields($must_params,$params) ) rsp_die_json(10001,'参数缺失'.implode(',',$empty_fileds) );

    	array_map(function($m)use($params,&$add_params){
    		$add_params[$m] = $params[$m];
    	},$must_params);
    	$not_need_params = ['warranty_time_begin','warranty_time_end','device_power','energy_tag_id','transfer_unit','transfer_time','device_remarks','creator'];
    	array_map(function($m)use($params,&$add_params){
    		if( isset($params[$m]) ) $add_params[$m] = $params[$m];
    	},$not_need_params);

    	return $add_params;
    }
}

