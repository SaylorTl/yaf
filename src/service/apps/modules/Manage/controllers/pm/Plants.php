<?php

use Project\SpaceModel;

class Plants extends Base {

	public function lists($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	if (!isTrueKey($params, 'page', 'pagesize')) rsp_die_json(10001, 'page pagesize 参数缺失或错误');
        $params['project_id'] = $this->project_id;
    	$lists = $this->pm->post('/plants/lists', $params);
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
        $total = $this->pm->post('/plants/count', $params);
        if ((int)$total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => count($data), 'lists' => $data]);
        rsp_success_json(['total' => (int)$total['content'], 'lists' => $data ]);
    }

    public function add($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	$add_params = $this->params_handel($params);
    	$plants_id = resource_id_generator(self::RESOURCE_TYPES['plants']);
        if(!$plants_id) rsp_die_json(10005,'添加失败');
        $add_params['plants_id'] = $plants_id;
        $add_params['creator'] =  $add_params['editor'] = $this->employee_id;
        $add_params['project_id'] = $this->project_id;
    	$result = $this->pm->post('/plants/add', $add_params);
    	if($result['code'] != 0) rsp_die_json($result['code'],$result['message']);
        //添加审计日志
        Comm_AuditLogs::push(1334, $plants_id, '添加绿植信息', 1323, $add_params, '成功');
    	rsp_success_json($result['content']);
    }

    public function update($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	if(isTrueKey($params,'plants_id')  === false) rsp_die_json(10001,'plants_id 参数缺失');
    	$update_params = $this->params_handel($params);
    	$update_params['plants_id'] = $params['plants_id'];

        $update_params['editor'] = $this->employee_id;
        $update_params['project_id'] = $this->project_id;
    	$result = $this->pm->post('/plants/update', $update_params);
    	if($result['code'] != 0) rsp_die_json($result['code'],$result['message']);
        //添加审计日志
        Comm_AuditLogs::push(
            1334,
            $params['plants_id'],
            '更新绿植信息',
            1324,
            $update_params,
            (!isset($result['code']) || $result['code'] != 0) ? '失败' : '成功'
        );
    	rsp_success_json($result['content']);
    }

    public function params_handel($params){
    	$must_params = ['plants_name','plants_extcode','type_tag_id','project_id','space_id','grow_tag_id','plants_time','cure_time','plants_address'];
    	if( $empty_fileds = get_empty_fields($must_params,$params) ) rsp_die_json(10001,'参数缺失'.implode(',',$empty_fileds) );

    	array_map(function($m)use($params,&$add_params){
    		$add_params[$m] = $params[$m];
    	},$must_params);
    	$not_need_params = ['plants_file_id','employee_id','maintain_explain','plants_remarks','plants_dbh','plants_high','crown_width','plants_area','prune_time','best_high','termite_cure_time'];
    	array_map(function($m)use($params,&$add_params){
    		if( isset($params[$m]) ) $add_params[$m] = $params[$m];
    	},$not_need_params);

    	return $add_params;
    }
}

