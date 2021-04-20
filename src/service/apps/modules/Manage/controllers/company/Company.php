<?php

class Company extends Base
{
	protected static $company_fileds = [
		'company_name',
		'company_type',
		'company_address',
		'company_liaisons',
        'country_id',
        'province_id',
        'city_id',
        'region_id',
	];
	protected static $liaision_fileds = [
		'liaison_name',
		'liaison_job',
		'liaison_tel',
		'liaison_email',
	];
	protected static $account_fileds = [
		'account_name',
		'open_bank',
		'account_num',
		'account_address',
		'open_bank_tel',
		'taxpayer_num',
        'account_bank',
	];
    public function lists($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	if (!isTrueKey($params, 'page', 'pagesize')) {
    	    rsp_die_json(10001, 'page pagesize 参数缺失或错误');
        }
        $company_ids = $this->getCompanyIds($params);
        if( $company_ids ){
            $params['company_ids'] = $company_ids;
            unset($company_ids);
        }
    	$lists = $this->company->post('/company/lists', $params);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        $province_ids = array_unique(array_column($lists['content'],'province_id'));
        $city_ids = array_unique(array_column($lists['content'],'city_id'));
        $region_ids = array_unique(array_column($lists['content'],'region_id'));
        $codes = array_merge($province_ids,$city_ids,$region_ids);
        $tmp = $this->addr->post('/addrcode/codes',['codes'=>implode(',',$codes) ]);
        $addrs = $tmp['code'] == 0 && !empty($tmp['content']) ? $tmp['content'] : [];

        // 员工
        $employee_ids = array_unique(array_merge(array_filter(array_column($lists['content'], 'creator')), array_filter(array_column($lists['content'], 'editor'))));
        $employees = $this->user->post('/employee/lists',['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        $data = array_map(function($m)use($addrs,$employees){
            $m['country_name'] = '中国';
            $m['province_name'] = getArraysOfvalue($addrs, $m['province_id'],'province_name');
            $m['city_name'] = getArraysOfvalue($addrs, $m['city_id'],'city_name');
            $m['region_name'] = getArraysOfvalue($addrs, $m['region_id'],'area_name');
            $m['creator_name'] = getArraysOfvalue($employees, $m['creator'], 'full_name');
            $m['editor_name'] = getArraysOfvalue($employees, $m['editor'], 'full_name');
            return $m;
        },$lists['content']);
        $total = $this->company->post('/company/count', $params);
        if ($total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => count($data), 'lists' => $data ]);
        rsp_success_json(['total' => (int)$total['content'], 'lists' => $data ]);
    }
    
    /**
     * 获取 company_id 集合
     * @param $params
     * @return array
     */
    private function getCompanyIds($params)
    {
        $query = [];
        $query['liaison_name_f'] = $params['liaison_name_f'] ?? null;
        $query['liaison_tel_f'] = $params['liaison_tel_f'] ?? null;
        $query = array_filter($query,function ($m){
            return !is_null($m) && $m !== '';
        });
        if (empty($query)) {
            return [];
        }
        $result = $this->company->post('/liaison/lists', $query);
        if (!isset($result['code']) || $result['code'] != 0) {
            rsp_die_json(10002, '查询失败 '.($result['message'] ?: ''));
        }
        $company_ids = array_unique(array_filter(array_column($result['content'], 'company_id')));
        return $company_ids ?: ['888888888888888888888888'];
    }

    public function add($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	$add_params = $this->params_handle($params);
        $company_id = resource_id_generator(self::RESOURCE_TYPES['company']);
        if(!$company_id) rsp_die_json(10003,'添加失败');
        
        $add_params['company_id'] = $company_id;
        $add_params['creator'] =  $add_params['editor'] = $this->employee_id;
    	$result = $this->company->post('/company/add', $add_params);
    	if($result['code'] != 0) rsp_die_json(10004,$result['message']);
        //添加审计日志
        Comm_AuditLogs::push(1326, $result['content'], '添加客户信息', 1323, $add_params, '成功');
    	rsp_success_json($result['content']);
    }

    public function update($params = []){
    	log_message(__METHOD__.'------'.json_encode($params) );
    	if(isTrueKey($params,'company_id') == false) rsp_die_json(10001,'company_id参数缺失');
    	$update_params = $this->params_handle($params);
    	$update_params['company_id'] = $params['company_id'];
        
        $update_params['editor'] = $this->employee_id;
    	$result = $this->company->post('/company/update', $update_params);
        //添加审计日志
        Comm_AuditLogs::push(
            1326,
            $update_params['company_id'],
            '更新客户',
            1324,
            $update_params,
            (isset($result['code']) && $result['code'] == 0) ? '成功' : '失败'
        );
    	if($result['code'] != 0) rsp_die_json(10004,$result['message']);

    	rsp_success_json('');
    }

    public function type_lists($params = []){
    	$lists = $this->company->post('/company/type/lists', $params);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);
        rsp_success_json(['total'=>count($lists['content']),'lists'=>$lists['content'] ]);
    }

    public function corporate_lists($params = []){
    	$lists = $this->company->post('/corporate/lists', $params);
    	if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);
        rsp_success_json(['total'=>count($lists['content']),'lists'=>$lists['content'] ]);
    }

    public function params_handle($params){
    	if( $empty_fileds = get_empty_fields(self::$company_fileds,$params) )  rsp_die_json(10001,'参数缺失'.implode(',',$empty_fileds) );
    	if(is_not_json($params['company_liaisons'])) rsp_die_json(10002,'company_liaisons格式错误');
    	$add_param  = [
            'city_id'         => $params['city_id'],
            'region_id'       => $params['region_id'],
            'country_id'      => $params['country_id'],
            'province_id'     => $params['province_id'],
    		'company_address' => $params['company_address'],
    		'company_name'    => $params['company_name'],
    		'company_type'    => $params['company_type'],
    	];	
    	if(isTrueKey($params,'company_files')){
    		if(is_not_json($params['company_files'])) rsp_die_json(10002,'company_files格式错误');
    		$add_param['company_files'] = $params['company_files'];
    	}
    	if(isTrueKey($params,'company_contracts')){
    		if(is_not_json($params['company_contracts'])) rsp_die_json(10002,'company_contracts格式错误');
    		$add_param['company_contracts'] = $params['company_contracts'];
    	}
    	if( isset($params['company_remarks']) ){
    		$add_param['company_remarks'] = $params['company_remarks'];
    	}
    	if( isTrueKey($params,'creator') ){
    		$add_param['creator'] = $params['creator'];
    	}
        $company_liaisons = json_decode($params['company_liaisons'],true);
    	foreach($company_liaisons as $k => $item){
            if(!array_diff(self::$liaision_fileds,get_empty_fields(self::$liaision_fileds,$item) ) ) unset($company_liaisons[$k]);
    	}
    	$add_param['company_liaisons'] = json_encode( $this->fileds_assign($company_liaisons,self::$liaision_fileds) );

    	if(isTrueKey($params,'company_accounts')){
    		if(is_not_json($params['company_accounts'])) rsp_die_json(10003,'company_accounts格式错误');
            $company_accounts = json_decode($params['company_accounts'],true);
	    	foreach($company_accounts as $k => $item){
	    		if(!array_diff(self::$account_fileds,get_empty_fields(self::$account_fileds,$item) ) ) unset($company_accounts[$k]); 
	    	}
	    	$add_param['company_accounts'] = json_encode( $this->fileds_assign($company_accounts,self::$account_fileds) );
    	}
    	return $add_param;
    }

    private function fileds_assign($params,$fileds){
    	return array_map(function($m)use($fileds){
    		$tmp = [];
    		foreach($fileds as $v){
    			if(isTrueKey($m,'liaison_id') ) $tmp['liaison_id'] = $m['liaison_id'];
    			if(isTrueKey($m,'account_id') ) $tmp['account_id'] = $m['account_id'];
    			$tmp[$v] = $m[$v];
    		}
    		return $tmp;
    	},$params);
    }
}