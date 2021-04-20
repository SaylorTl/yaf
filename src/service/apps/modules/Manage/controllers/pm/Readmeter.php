<?php

class Readmeter extends Base {

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
    	$lists = $this->pm->post('/read/meter/lists', $params);
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
        $total = $this->pm->post('/read/meter/count', $params);
        if ($total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => count($data), 'lists' => $data]);
        rsp_success_json(['total' => (int)$total['content'], 'lists' => $data ]);
    }

    public function add($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	$add_params = $this->params_handel($params);
        
        $add_params['creator'] =  $add_params['editor'] = $this->employee_id;
        $add_params['project_id'] = $this->project_id;
        $drm_id = resource_id_generator(self::RESOURCE_TYPES['readmeter']);
        if(!$drm_id) rsp_die_json(10003,'添加失败');
        $add_params['drm_id'] = $drm_id;
    	$result = $this->pm->post('/read/meter/add', $add_params);
    	if($result['code'] != 0) rsp_die_json(10004,$result['message']);
        //添加审计日志
        Comm_AuditLogs::push(1337, $drm_id, '添加抄表', 1323, $add_params, '成功');
    	rsp_success_json($result['content']);
    }

    public function update($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	if(isTrueKey($params,'drm_id')  === false) rsp_die_json(10001,'drm_id 参数缺失');
    	$update_params = $this->params_handel($params);
    	$update_params['drm_id'] = $params['drm_id'];

        $update_params['editor'] = $this->employee_id;
        $update_params['project_id'] = $this->project_id;
    	$result = $this->pm->post('/read/meter/update', $update_params);
    	if($result['code'] != 0) rsp_die_json(10004,$result['message']);
        //添加审计日志
        Comm_AuditLogs::push(
            1337,
            $params['drm_id'],
            '更新抄表信息',
            1324,
            $update_params,
            (!isset($result['code']) || $result['code'] != 0) ? '失败' : '成功'
        );
    	rsp_success_json('');
    }

    public function params_handel($params){
    	$must_params = ['device_id','project_id','meter_tag_id','read_meter_time','last_reading','now_reading','now_reading','reading_user','actual_usage'];
    	if( $empty_fileds = get_empty_fields($must_params,$params) ) rsp_die_json(10001,'参数缺失'.implode(',',$empty_fileds) );
    	array_map(function($m)use($params,&$add_params){
    		$add_params[$m] = $params[$m];
    	},$must_params);

        $add_params['actual_usage'] = $params['actual_usage'];
    	if( isset($params['drm_remarks']) ) $add_params['drm_remarks'] = $params['drm_remarks'];
    	return $add_params;
    }
}