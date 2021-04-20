<?php

class News extends Base {

	public function car_lists($params = []){
		log_message(__METHOD__.'------'.json_encode($params) );
		if(isTrueKey($params,'project_id','mobile') == false) rsp_die_json(10001,'参数缺失');
		$user_info = $this->adapter->post('/news/process',array_merge($params,['action'=>'user_detail']) );
		if($user_info['code'] != 0 ) rsp_die_json(10002,$user_info['message']);
		if(empty($user_info['content']))  rsp_success_json(['total'=>0,'lists'=>[]],'success');
		
		$params['user_id'] = $user_info['content']['user_id'];
		$params['action'] = 'get_plates';
		$rsp = $this->adapter->post('/news/process',$params);
		if($rsp['code'] != 0) rsp_die_json(10002,$rsp['message']);
		if( empty($rsp['content']) ) rsp_success_json(['total'=> 0,'lists'=>[] ],'success');
		rsp_success_json(['total'=>count($rsp['content']),'lists'=>$rsp['content']]);
	}


	public function contract($params = []){
		log_message(__METHOD__.'------'.json_encode($params) );
		if( !isTrueKey($params,'mobile') && !isTrueKey($params,'car_id') ){
			rsp_die_json(10001,'参数缺失');
		}
		if( !isTrueKey($params,'project_id') ){
			rsp_die_json(10001,'project_id参数缺失');
		}
		if( isTrueKey($params,'mobile') ){
			$user_info = $this->adapter->post('/news/process',array_merge($params,['action'=>'user_detail']) );
			if($user_info['code'] != 0 ) rsp_die_json(10002,$user_info['message']);
			if(empty($user_info['content']))  rsp_success_json(['total'=>0,'lists'=>[]],'success');
			$params['user_id'] = $user_info['content']['user_id'];
		}
		if(!isTrueKey($params,'car_id') ) $params['car_id'] = 0;
		$rsp = $this->adapter->post('/news/process',array_merge($params,['action'=>'contract']) );
		if($rsp['code'] != 0) rsp_die_json(10002,$rsp['message']);
		if( empty($rsp['content']) ) rsp_success_json(['total'=> 0,'lists'=>[] ],'success');
		rsp_success_json(['total'=>count($rsp['content']),'lists'=>$rsp['content']],'success');
	}

	public function contract_cost($params = []){
		log_message(__METHOD__.'------'.json_encode($params) );
		if(isTrueKey($params,'project_id','contract_id','month_total','user_id') == false) rsp_die_json(10001,'参数缺失');

		$rsp = $this->adapter->post('/news/process',array_merge($params,['action'=>'contract_cost']) );
		if($rsp['code'] != 0) rsp_die_json(10002,$rsp['message']);
		if( empty($rsp['content']) ) rsp_success_json([],'success');
		rsp_success_json($rsp['content'],'success');
	}

	public function room($params = []){
		log_message(__METHOD__.'------'.json_encode($params) );
		if(isTrueKey($params,'project_id','mobile') == false) rsp_die_json(10001,'参数缺失');
		$user_info = $this->adapter->post('/news/process',array_merge($params,['action'=>'user_detail']) );
		if($user_info['code'] != 0 ) rsp_die_json(10002,$user_info['message']);
		if(empty($user_info['content']))  rsp_success_json(['total'=>0,'lists'=>[]],'success');
		
		$params['user_id'] = $user_info['content']['user_id'];
		$params['action'] = 'room';
		$rsp = $this->adapter->post('/news/process',$params);
		if($rsp['code'] != 0) rsp_die_json(10002,$rsp['message']);
		if( empty($rsp['content']) ) rsp_success_json(['total'=> 0,'lists'=>[] ],'success');
		rsp_success_json(['total'=>count($rsp['content']),'lists'=>$rsp['content']]);
	}

}