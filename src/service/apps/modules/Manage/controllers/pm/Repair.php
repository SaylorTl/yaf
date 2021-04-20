<?php

class Repair extends Base {

	public function lists($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	if (!isTrueKey($params, 'page', 'pagesize')) rsp_die_json(10001, 'page pagesize 参数缺失或错误');
        if(is_not_empty($params,'project_name')){
            $tmp = $this->pm->post('/project/projects',['project_name'=>$params['project_name'] ]);
            if($tmp['code'] != 0 || empty($tmp['content']) ) rsp_success_json(['total' => 0, 'lists' => []]);
            $params['project_ids'] = array_unique(array_column($tmp['content'],'project_id'));
            unset($params['project_name']);
        }

        if( is_not_empty($params,'device_name') || is_not_empty($params,'device_extcode') ){
            $device_list_param = [];
            if(isset($params['device_name'])){
                $device_list_param['device_name']= $params['device_name']; unset($params['device_name']);
            }
            if(isset($params['device_extcode'])){
                $device_list_param['device_extcode']= $params['device_extcode']; unset($params['device_extcode']);
            }
            $tmp = $this->device->post('/device/lists',$device_list_param );
            if($tmp['code'] != 0 || empty($tmp['content']) ) rsp_success_json(['total' => 0, 'lists' => []]);
            $params['device_ids'] = array_unique(array_column($tmp['content'],'device_id'));
        }
        $params['project_id'] = $this->project_id;
    	$lists = $this->pm->post('/repair/lists', $params);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        // 设备
        $devices = $this->device->post('/device/lists',['device_ids' => array_unique(array_filter(array_column($lists['content'], 'device_id')))] );
        $devices = ($devices['code'] === 0 && $devices['content']) ? many_array_column($devices['content'], 'device_id') : [];

        // 员工
        $employee_ids = array_unique(array_merge(array_filter(array_column($lists['content'], 'creator')), array_filter(array_column($lists['content'], 'editor'))));
        $employees = $this->user->post('/employee/lists',['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        $data = array_map(function ($m) use ($employees, $devices) {
            $m['device_name'] = getArraysOfvalue($devices, $m['device_id'], 'device_name');
            $m['device_extcode'] = getArraysOfvalue($devices, $m['device_id'], 'device_extcode');
            $m['creator_name'] = getArraysOfvalue($employees, $m['creator'], 'full_name');
            $m['editor_name'] = getArraysOfvalue($employees, $m['editor'], 'full_name');
            return $m;
        },$lists['content']);


        $total = $this->pm->post('/repair/count', $params);
        if ($total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => count($data), 'lists' => $data ]);
        rsp_success_json(['total' => (int)$total['content'], 'lists' => $data ]);
    }

    public function add($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	$must_params = ['project_id','device_id','repair_user','repair_time','repair_detail'];
    	if( $empty_fileds = get_empty_fields($must_params,$params) ) rsp_die_json(10001,'参数缺失'.implode(',',$empty_fileds));

    	array_map(function($m)use($params,&$add_params){
    		$add_params[$m] = $params[$m];
    	},$must_params);
        
    	if( isset($params['repair_remarks']) ) $add_params['repair_remarks'] = $params['repair_remarks'];
        $add_params['creator'] =  $add_params['editor'] = $this->employee_id;
        $add_params['project_id'] = $this->project_id;
        $repair_id = resource_id_generator(self::RESOURCE_TYPES['repair']);
        if(!$repair_id) rsp_die_json(10003,'添加失败');
        $add_params['repair_id'] = $repair_id;
    	$result = $this->pm->post('/repair/add', $add_params );
    	if($result['code'] != 0) rsp_die_json(10004,$result['message']);
        //添加审计日志
        Comm_AuditLogs::push(1338, $repair_id, '添加维修记录', 1323, $add_params, '成功');
    	rsp_success_json($result['content']);
    }

    public function update($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	if(isTrueKey($params,'repair_id')  === false) rsp_die_json(10001,'repair_id 参数缺失');
    	$must_params = ['service_user','service_time','fault_detail','service_content','overhaul_state','service_amount','check_user'];
        if( $empty_fileds = get_empty_fields($must_params,$params) ) rsp_die_json(10001,'参数缺失'.implode(',',$empty_fileds));

    	array_map(function($m)use($params,&$update_params){
    		$update_params[$m] = $params[$m];
    	},$must_params);
    	$update_params['repair_id'] = $params['repair_id'];

        $update_params['editor'] = $this->employee_id;
        $update_params['project_id'] = $this->project_id;
    	if( isset($params['service_remarks']) ) $update_params['service_remarks'] = $params['service_remarks'];
    	$result = $this->pm->post('/repair/update', $update_params);
    	if($result['code'] != 0) rsp_die_json(10004,$result['message']);
        //添加审计日志
        Comm_AuditLogs::push(
            1338,
            $params['repair_id'],
            '更新维修记录',
            1324,
            $update_params,
            (!isset($result['code']) || $result['code'] != 0) ? '失败' : '成功'
        );
    	rsp_success_json('');
    }
}