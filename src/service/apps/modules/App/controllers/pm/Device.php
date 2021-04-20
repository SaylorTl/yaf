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

    /**
     * 门卡二维码根据设备id获取设备详情
     * @param array $params
     */
    public function device_v2_show($params = []){
	    log_message(__METHOD__.'------'.json_encode($params) );
    	if (!isTrueKey($params, 'device_id')) rsp_die_json(10001, '设备uuid参数缺失');
        $device = $this->pm->post('/device/v2/lists',['page' => 1, 'pagesize' => 1, 'device_id' => $params['device_id']]);

        if($device['code'] != 0 || empty($device['content']) ) rsp_die_json(10002,$device['message']);
        rsp_success_json($device['content'][0],'success');
    }

}

