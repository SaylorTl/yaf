<?php

use Project\SpaceModel;

class Yardrent extends Base {

	public function lists($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	if (!isTrueKey($params, 'page', 'pagesize')) rsp_die_json(10001, 'page pagesize 参数缺失或错误');
        $params['project_id'] = $this->project_id;
    	$lists = $this->pm->post('/yardrent/lists', $params);
        if ((int)$lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        // 员工
        $employee_ids = array_unique(
            array_merge(
                array_filter(array_column($lists['content'], 'creator')), 
                array_filter(array_column($lists['content'], 'editor')),
                array_filter(array_column($lists['content'], 'employee_id'))
            )
        );
        $employees = $this->user->post('/employee/lists',['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        // space
        $space_ids = array_unique(array_filter(array_column($lists['content'], 'space_id')));
        $space_branches = $this->pm->post('/space/batchBranch', ['space_ids' => $space_ids]);
        $space_branches = $space_branches['content'] ?? [];

        $data = array_map(function($m)use($employees, $space_branches){
            $m['creator_name'] = getArraysOfvalue($employees, $m['creator'], 'full_name');
            $m['editor_name'] = getArraysOfvalue($employees, $m['editor'], 'full_name');
            $m['employee_name'] = getArraysOfvalue($employees, $m['employee_id'], 'full_name');
            $m['employee_mobile'] = getArraysOfvalue($employees, $m['employee_id'], 'mobile');
            $branch_info = SpaceModel::parseBranch($space_branches[$m['space_id']] ?? []);
            $m['space_name_full'] = $branch_info['space_name_full'] ?? '';
            return $m;
        },$lists['content']);    
        $total = $this->pm->post('/yardrent/count', $params);
        if ((int)$total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => count($data), 'lists' => $data]);
        rsp_success_json(['total' => (int)$total['content'], 'lists' => $data ]);
    }

    public function add($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	$add_params = $this->params_handel($params);
    	$yard_rent_id = resource_id_generator(self::RESOURCE_TYPES['yardrent']);
        if(!$yard_rent_id) rsp_die_json(10005,'添加失败');
        $add_params['yard_rent_id'] = $yard_rent_id;
        $add_params['creator'] =  $add_params['editor'] = $this->employee_id;
        $add_params['project_id'] = $this->project_id;

    	$result = $this->pm->post('/yardrent/add', $add_params);
    	if($result['code'] != 0) rsp_die_json($result['code'],$result['message']);
        //添加审计日志
        Comm_AuditLogs::push(1339, $yard_rent_id, '添加场地', 1323, $add_params, '成功');
    	rsp_success_json($result['content']);
    }

    public function update($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	if(isTrueKey($params,'yard_rent_id')  === false) rsp_die_json(10001,'yard_rent_id 参数缺失');
    	$update_params = $this->params_handel($params);
    	$update_params['yard_rent_id'] = $params['yard_rent_id'];

        $update_params['editor'] = $this->employee_id;
        $update_params['project_id'] = $this->project_id;
    	$result = $this->pm->post('/yardrent/update', $update_params);
        //添加审计日志
        Comm_AuditLogs::push(
            1339,
            $params['yard_rent_id'],
            '更新场地',
            1324,
            $update_params,
            (isset($result['code']) && $result['code'] == 0) ? '成功' : '失败'
        );
    	if($result['code'] != 0) rsp_die_json($result['code'],$result['message']);
    	rsp_success_json($result['content']);
    }

    public function params_handel($params){
    	$must_params = ['yard_name','yard_type_tag_id','yard_status_tag_id','project_id','space_id','yard_water_tag_id','yard_elec_tag_id','yard_area'];
    	if( $empty_fileds = get_empty_fields($must_params,$params) ) rsp_die_json(10001,'参数缺失'.implode(',',$empty_fileds) );

    	array_map(function($m)use($params,&$add_params){
    		$add_params[$m] = $params[$m];
    	},$must_params);
    	$not_need_params = ['employee_id','yard_area_price','detail_address','yard_remarks','rent_time_begin','rent_time_end','price_cycle_tag_id'];
    	array_map(function($m)use($params,&$add_params){
    		if( isset($params[$m]) ) $add_params[$m] = $params[$m];
    	},$not_need_params);

    	return $add_params;
    }
}

