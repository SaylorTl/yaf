<?php

class Device extends Base {

	public function lists($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	if (!isTrueKey($params, 'page', 'pagesize')) rsp_die_json(10001, 'page pagesize 参数缺失或错误');
        if(is_not_empty($params,'project_name') ){
            $tmp = $this->pm->post('/project/projects',['project_name'=>$params['project_name'] ]);
            if($tmp['code'] != 0 || empty($tmp['content']) ) rsp_success_json(['total' => 0, 'lists' => []]);
            $params['project_ids'] = array_unique(array_column($tmp['content'],'project_id'));
            unset($params['project_name']);
        }
        $params['project_id'] = $this->project_id;
    	$lists = $this->pm->post('/device/lists', $params);
        if ((int)$lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        // 员工
        $employee_ids = array_unique(array_merge(array_filter(array_column($lists['content'], 'creator')), array_filter(array_column($lists['content'], 'editor'))));
        $employees = $this->user->post('/employee/lists',['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        $data = array_map(function($m)use($employees){
            $m['creator_name'] = getArraysOfvalue($employees, $m['creator'], 'full_name');
            $m['editor_name'] = getArraysOfvalue($employees, $m['editor'], 'full_name');
            return $m;
        },$lists['content']);    
        $total = $this->pm->post('/device/count', $params);
        if ((int)$total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => count($data), 'lists' => $data]);
        rsp_success_json(['total' => (int)$total['content'], 'lists' => $data ]);
    }

    public function add($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	$add_params = $this->params_handel($params);
    	$device_id = resource_id_generator(self::RESOURCE_TYPES['device']);
        if(!$device_id) rsp_die_json(10003,'添加失败');
        
        $add_params['device_id'] = $device_id;
        $add_params['creator'] =  $add_params['editor'] = $this->employee_id;
        $add_params['project_id'] = $this->project_id;
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
        $update_params['project_id'] = $this->project_id;
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

