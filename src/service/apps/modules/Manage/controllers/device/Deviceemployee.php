<?php

class Deviceemployee extends Base {

	public function lists($post = [])
    {
        if (!isTrueKey($post, ...['page', 'pagesize', 'device_id'])) rsp_error_tips(10001, 'page pagesize device_id');

        $device = $this->pm->post('/device/v2/lists',['page' => 1, 'pagesize' => 1, 'device_id' => $post['device_id']]);
        $device = ($device['code'] === 0 && $device['content']) ? $device['content'][0] : [];
        if (!$device) rsp_success_json(['total' => 0, 'lists' => []]);
        $post['project_ids'] = [$device['project_id']];

        $result = $this->user->post('/employee/userlist',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }
        $lists = $result['content']['lists'];

        $employee_ids = array_filter(array_unique(array_column($lists,'employee_id')));
        $device_employees = $this->pm->post('/device/employee/lists', ['device_id' => $post['device_id'], 'employee_ids' => $employee_ids]);
        $device_employees = ($device_employees['code'] === 0 && $device_employees['content']) ? many_array_column($device_employees['content'], 'employee_id') : [];

        $creator_arr = array_filter(array_unique(array_column($lists,'creator')));
        $editor_arr = array_filter(array_unique(array_column($lists,'editor')));
        $leader_arr = array_unique(array_filter(array_column($lists,'leader')));
        $creator_res = $this->user->post('/employee/lists',['employee_ids'=>array_merge($creator_arr,$editor_arr,$leader_arr)]);
        if($creator_res['code']!=0){
            rsp_die_json(10002,$creator_res['message']);
        }
        $creator_content =  array_column($creator_res['content'],null,'employee_id');

        $project_arr = array_unique(array_filter(array_column($lists,'project_id')));
        $project_res = $this->pm->post('/project/lists',['project_ids'=>$project_arr]);
        if($project_res['code']!=0){
            rsp_die_json(10002,$project_res['message']);
        }
        $project_content = array_column($project_res['content'],null,'project_id');
        $frame_arr = array_unique(array_filter(array_column($lists,'frame_id')));
        $frame_res = $this->pm->post('/framelists',['frame_ids'=>implode(',',$frame_arr)]);
        if($frame_res['code']!=0){
            rsp_die_json(10002,$frame_res['message']);
        }
        $frame_content = array_column($frame_res['content'],null,'frame_id');
        foreach($result['content']['lists'] as $key=>$value){
            $result['content']['lists'][$key]['departure_time'] = !empty($value['departure_time'])?date('Y-m-d H:i:s',$value['departure_time']):'';
            $result['content']['lists'][$key]['entry_time'] = !empty($value['entry_time'])?date('Y-m-d H:i:s',$value['entry_time']):'';
            $result['content']['lists'][$key]['creator'] = isset($creator_content[$value['creator']])?$creator_content[$value['creator']]['full_name']:'';
            $result['content']['lists'][$key]['editor'] = isset($creator_content[$value['editor']])?$creator_content[$value['editor']]['full_name']:'';
            $result['content']['lists'][$key]['project_name'] =isset($project_content[$value['project_id']])?$project_content[$value['project_id']]['project_name']:'';
            $result['content']['lists'][$key]['labor_begin_time'] = date('Y-m-d',$value['labor_begin_time']);
            $result['content']['lists'][$key]['labor_end_time'] = date('Y-m-d',$value['labor_end_time']);
            $result['content']['lists'][$key]['frame_name'] =isset($frame_content[$value['frame_id']])?$frame_content[$value['frame_id']]['frame_name']:'';
            $result['content']['lists'][$key]['leader_name'] =isset($creator_content[$value['leader']])?$creator_content[$value['leader']]['full_name']:'';
            $result['content']['lists'][$key]['has'] = getArraysOfvalue($device_employees,$value['employee_id'], 'has') ?: 'Y';
        }
        rsp_success_json(['total' => $result['content']['count'], 'lists' => $result['content']['lists']],'查询成功');
    }

    public function toggle($params = [])
    {
        if (!isTrueKey($params, ...['device_id', 'employee_id'])) rsp_error_tips(10001, 'device_id employee_id');
        $res = $this->pm->post('/device/employee/toggle', $params);
        if ($res['code'] !== 0) rsp_error_tips(10006);
        rsp_success_json(1);
    }
}

