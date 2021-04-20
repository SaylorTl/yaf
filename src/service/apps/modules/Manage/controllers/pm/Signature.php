<?php

class Signature extends Base {

    public function add($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	$add_params = $this->params_handel($params);
    	$result = $this->pm->post('/signature/add', $add_params);
    	if($result['code'] != 0) rsp_die_json($result['code'],$result['message']);
    	rsp_success_json($result['content']);
    }

    public function update($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	if(isTrueKey($params,'signature_id')  === false) rsp_die_json(10001,'signature_id 参数缺失');
    	$update_params = $this->params_handel($params);
    	$update_params['signature_id'] = $params['signature_id'];

    	$result = $this->pm->post('/signature/update', $update_params);
    	if($result['code'] != 0) rsp_die_json($result['code'],$result['message']);
    	rsp_success_json($result['content']);
    }

    public function show($params = []){
        log_message(__METHOD__.'------'.json_encode($params) );
        $result = $this->pm->post('/signature/show', $params);
    	if($result['code'] != 0) rsp_die_json($result['code'],$result['message']);
    	rsp_success_json($result['content']);
    }

    public function params_handel($params){
    	$must_params = ['seal','template','signature_type','project_id','status'];
    	if( $empty_fileds = get_empty_fields($must_params,$params) ) rsp_die_json(10001,'参数缺失'.implode(',',$empty_fileds) );

    	array_map(function($m)use($params,&$add_params){
    		$add_params[$m] = $params[$m];
    	},$must_params);
    	return $add_params;
    }

}


