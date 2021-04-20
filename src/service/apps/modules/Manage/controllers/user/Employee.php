<?php

final class Employee extends Base
{

    /**
     * @param array $post
     * @throws Exception
     * 员工列表
     */
    public function EmployeeList($post=[])
    {
        unsetEmptyParams($post);
        $result = $this->user->post('/employee/userlist',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }
        $lists = $result['content']['lists'];
        $nation_arr = array_column($lists,'nation_tag_id');
        $political_arr = array_column($lists,'political_tag_id');
        $license_arr = array_column($lists,'license_tag_id');
        $employee_arr = array_column($lists,'employee_status_tag_id');
        $education_arr = array_column($lists,'education_tag_id');
        $labor_arr = array_column($lists,'labor_type_tag_id');
        $sex_arr = array_column($lists,'sex');
        $tags = array_filter(array_merge($nation_arr,$political_arr,$license_arr,$employee_arr,$education_arr,
            $labor_arr,$sex_arr));
        $tag_res = $this->tag->post('/tag/lists',['tag_ids'=>implode(',',$tags)]);
        if($tag_res['code']!=0){
            rsp_die_json(10002,$tag_res['message']);
        }
        $tag_content = array_column($tag_res['content'],null,'tag_id');
        $creator_arr = array_filter(array_unique(array_column($lists,'creator')));
        $editor_arr = array_filter(array_unique(array_column($lists,'editor')));
        $creator_res = $this->user->post('/employee/lists',['employee_ids'=>array_merge($creator_arr,$editor_arr)]);
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
        //汇报人岗位
        $leader_job_info = $this->getLeaderJobInfo(array_column($lists,'leader'));
        //员工岗位
        $job_info  = $this->getJobInfo(array_column($lists,'employee_id'));
        
        foreach($result['content']['lists'] as $key=>$value){
            $result['content']['lists'][$key]['nation_tag_name'] = isset($tag_content[$value['nation_tag_id']])?$tag_content[$value['nation_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['political_tag_name'] = isset($tag_content[$value['political_tag_id']])?$tag_content[$value['political_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['labor_type_tag_name'] = isset($tag_content[$value['labor_type_tag_id']])?$tag_content[$value['labor_type_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['license_tag_name'] = isset($tag_content[$value['license_tag_id']])?$tag_content[$value['license_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['employee_status'] =isset($tag_content[$value['employee_status_tag_id']])?$tag_content[$value['employee_status_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['education_tag_name'] = isset($tag_content[$value['education_tag_id']])?$tag_content[$value['education_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['departure_time'] = !empty($value['departure_time'])?date('Y-m-d H:i:s',$value['departure_time']):'';
            $result['content']['lists'][$key]['entry_time'] = !empty($value['entry_time'])?date('Y-m-d H:i:s',$value['entry_time']):'';
            $result['content']['lists'][$key]['creator'] = isset($creator_content[$value['creator']])?$creator_content[$value['creator']]['full_name']:'';
            $result['content']['lists'][$key]['editor'] = isset($creator_content[$value['editor']])?$creator_content[$value['editor']]['full_name']:'';
            $result['content']['lists'][$key]['project_name'] =isset($project_content[$value['project_id']])?$project_content[$value['project_id']]['project_name']:'';
            $result['content']['lists'][$key]['labor_begin_time'] = !empty($value['labor_begin_time'])?date('Y-m-d',$value['labor_begin_time']):0;
            $result['content']['lists'][$key]['labor_end_time'] = !empty($value['labor_end_time'])?date('Y-m-d',$value['labor_end_time']):0;
            $result['content']['lists'][$key]['leader_job_name'] =isset($leader_job_info[$value['leader']]) ?$leader_job_info[$value['leader']]['job_name']:'';
            $result['content']['lists'][$key]['job_info'] =isset($job_info[$value['employee_id']]) ?$job_info[$value['employee_id']]:[];
        }
        rsp_success_json($result['content'],'查询成功');
    }
    
    /**
     * 获取汇报人岗位信息
     * @param $employee_ids
     * @return array
     */
    private function getLeaderJobInfo($job_ids)
    {
        $job_ids = array_filter(array_unique($job_ids));
        $job_info = [];
        if ($job_ids) {
            $job_info = $this->pm->post('/job/simpleLists', ['job_ids' => $job_ids]);
            $job_info = $job_info['code'] == 0 && !empty($job_info['content']) ? $job_info['content'] : [];
            $job_name_tag_ids = array_filter(array_unique(array_column($job_info, 'job_name_tag_id')));
            $tag_info = [];
            if ($job_name_tag_ids) {
                $tag_info = $this->tag->post('/tag/lists', ['tag_ids' => $job_name_tag_ids, 'nolevel' => 'Y']);
                $tag_info = $tag_info['code'] == 0 ? array_column($tag_info['content'], null, 'tag_id') : [];
            }
            $job_info = array_map(function ($m) use ($tag_info) {
                return [
                    'job_id' => $m['job_id'],
                    'job_name' => getArraysOfvalue($tag_info, $m['job_name_tag_id'], 'tag_name'),
                ];
            }, $job_info);
        }
        return array_column($job_info, null, 'job_id');
    }
    
    /**
     * 获取员工的岗位信息
     * @param $employee_ids
     * @return array
     */
    private function getJobInfo($employee_ids)
    {
        $employee_ids = array_filter(array_unique($employee_ids));
        $employee_job_info = $this->user->post('/employeejob/lists', ['employee_ids' => $employee_ids]);
        $employee_job_info = $employee_job_info['code'] == 0 ? $employee_job_info['content'] : [];
        $job_ids = array_filter(array_unique(array_column($employee_job_info, 'job_id')));
        $job_info = [];
        if ($job_ids) {
            $job_info = $this->pm->post('/job/simpleLists', ['job_ids' => $job_ids]);

            $job_info = ($job_info['code'] == 0 && !empty($job_info['content'])) ? $job_info['content'] : [];

            $job_name_tag_ids = array_filter(array_unique(array_column($job_info, 'job_name_tag_id')));
            $frame_ids = array_filter(array_unique(array_column($job_info, 'frame_id')));
            $tag_info = $frame_info = [];
            if ($job_name_tag_ids) {
                $tag_info = $this->tag->post('/tag/lists', ['tag_ids' => $job_name_tag_ids, 'nolevel' => 'Y']);
                $tag_info = $tag_info['code'] == 0 ? array_column($tag_info['content'], null, 'tag_id') : [];
            }
            if ($frame_ids) {
                $frame_info = $this->pm->post('/frameV2/lists', ['frame_ids' => $frame_ids]);
                $frame_info = $frame_info['code'] == 0 ? array_column($frame_info['content'], null, 'frame_id') : [];
            }
            $job_info = array_map(function ($m) use ($tag_info, $frame_info) {
                return [
                    'job_id' => $m['job_id'],
                    'job_name_tag_id' => $m['job_name_tag_id'],
                    'job_name' => getArraysOfvalue($tag_info, $m['job_name_tag_id'], 'tag_name'),
                    'frame_id' => $m['frame_id'],
                    'frame_name' => getArraysOfvalue($frame_info, $m['frame_id'], 'frame_name'),
                ];
            }, $job_info);
            $job_info = array_column($job_info, null, 'job_id');
        }
        $employee_job_info = array_map(function ($m) use ($job_info) {
            return [
                'employee_id' => $m['employee_id'],
                'job_id' => $m['job_id'],
                'job_name_tag_id' => getArraysOfvalue($job_info, $m['job_id'], 'job_name_tag_id'),
                'job_name' => getArraysOfvalue($job_info, $m['job_id'], 'job_name'),
                'frame_id' => getArraysOfvalue($job_info, $m['job_id'], 'frame_id'),
                'frame_name' => getArraysOfvalue($job_info, $m['job_id'], 'frame_name'),
            ];
        }, $employee_job_info);
        
        $data = [];
        foreach ($employee_job_info as $value){
            $employee_id = $value['employee_id'];
            unset($value['employee_id']);
            if( !isset($data[$employee_id]) ){
                $data[$employee_id] = [];
            }
            $data[$employee_id][] = $value;
        }
        return $data;
    }
    
    /**
     * @param array $post
     * @throws Exception
     * 员工添加
     */
    public function EmployeeAdd($post=[])
    {
        $check_params_info = checkEmptyParams($post, ['full_name','mobile','sex','nation_tag_id','birth_day','license_tag_id',
            'political_tag_id','license_num','education_tag_id','employee_status_tag_id','entry_time','labor_type_tag_id',
            'labor_begin_time','labor_end_time','job_ids']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        if( !is_array($post['job_ids']) ){
            rsp_die_json(10001, '岗位参数的数据类型错误');
        }
        //检查岗位
        $post['job_ids'] = array_filter(array_unique($post['job_ids']));
        $this->checkJobIds(isTrueKey($post,'leader') ? array_merge($post['job_ids'],[$post['leader']]) : $post['job_ids']);
        
        $employee_res = $this->resource->post('/resource/id/generator',['type_name'=>'employee']);
        if($employee_res['code']!=0 || ($employee_res['code']==0 && empty($employee_res['content']))){
            rsp_die_json(10001, $employee_res['message']);
        }
        $post['employee_id'] = $employee_res['content'];
        $post['creator'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
        $post['editor'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
        $post['frame_id'] = '';
        $result = $this->user->post('/employee/addemployee',$post);
        if($result['code']!=0 ){
            rsp_die_json(10005,$result['message']);
        }
        //添加岗位
        $add_res = $this->addEmployeeJob($post['employee_id'],$post['job_ids']);
        if ($add_res === false) {
            rsp_die_json(10005, '岗位关联失败');
        }
        //添加审计日志
        Comm_AuditLogs::push(1329, $result['content'], '添加员工', 1323, $post, '成功');
        rsp_success_json($result['content'],'添加成功');
    }

    /**
     * @param array $post
     * @throws Exception  creator  editor
     * 附加信息展示
     */
    public function Employeeextlist($post=[])
    {
        $result = $this->user->post('/employee/extlist',$post);
        if($result['code']!=0){
            rsp_die_json(10002,$result['message']);
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json(['cert_list'=>[],'emergency_list'=>[]],'查询成功');
        }
        if(!empty($result['content']['cert_list'])){
            foreach($result['content']['cert_list'] as $key=>$value){
                $result['content']['cert_list'][$key]['cert_begin_time'] = !empty($value['cert_begin_time'])?date('Y-m-d',$value['cert_begin_time']):'';
                $result['content']['cert_list'][$key]['cert_end_time'] = !empty($value['cert_end_time'])?date('Y-m-d',$value['cert_end_time']):'';
            }
        }
        rsp_success_json($result['content'],'查询成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 员工添加
     */
    public function EmployeeUpdate($post=[])
    {
        $check_params_info = checkEmptyParams($post, ['employee_id','epy_ext_id','full_name','mobile','sex','nation_tag_id',
            'birth_day','license_tag_id','political_tag_id','license_num','education_tag_id','employee_status_tag_id',
            'entry_time','labor_type_tag_id','labor_begin_time','labor_end_time','job_ids']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        if( !is_array($post['job_ids']) ){
            rsp_die_json(10001, '岗位参数的数据类型错误');
        }
        //查询员工是否存在
        $employee_info = $this->user->post('/employee/userlist', ['employee_id' => $post['employee_id']]);
        if ($employee_info['code'] != 0) {
            rsp_die_json(10002, '员工信息查询失败');
        }
        if (empty($employee_info['content']['count'])) {
            rsp_die_json(10003, '员工不存在');
        }
        //检查岗位
        $post['job_ids'] = array_filter(array_unique($post['job_ids']));
        $this->checkJobIds(isTrueKey($post,'leader') ? array_merge($post['job_ids'],[$post['leader']]) : $post['job_ids']);
        $post['frame_id'] = '';
        $post['editor'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
        $result = $this->user->post('/employee/updateuser',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        //添加审计日志
        Comm_AuditLogs::push(
            1329,
            $post['employee_id'],
            '更新员工信息',
            1324,
            $post,
            (isset($result['code']) && $result['code'] == 0) ? '成功' : '失败'
        );
        $cfg = getConfig('ms.ini');
        $oauthUrl = $cfg->auth2->url ?? '';
        curl_json("post",$oauthUrl."/redis/delete/authUser",['access_token'=>session_id(),'employee_id'=>$post['employee_id']]);
    
        $employee_job_info = $this->user->post('/employeejob/lists', ['employee_id' => $post['employee_id']]);
        if( $employee_job_info['code']!=0 ){
            rsp_die_json('10002','岗位信息更新失败¹');
        }
        $job_ids = array_column($employee_job_info['content'],'job_id');
        $add_res = $this->addEmployeeJob($post['employee_id'],array_diff($post['job_ids'],$job_ids));
        $del_res = $this->delEmployeeJob($post['employee_id'],array_diff($job_ids,$post['job_ids']));
        if( $add_res === false || $del_res === false ){
            rsp_die_json('10005','岗位信息更新失败²');
        }

        //事件触发器推送
        $result = Comm_EventTrigger::push('wos_trail_user_update', ['employee_id' => $post['employee_id']]);
        if (empty($result)) {
            info(__METHOD__, ['error' => '员工更新事件触发器推送失败', 'employee_id' => $post['employee_id']]);
        }

        //如果项目信息更改且原所属项目有值，则更新员工排班数据
        /*if ($employee_info['content']['lists'][0]['project_id'] != $post['project_id'] && $employee_info['content']['lists'][0]['project_id']) {
            $res = $this->user->post('/employee/schedule/updateProjectChanging', ['project_id' => $employee_info['content']['lists'][0]['project_id'], 'employee_id' => $post['employee_id'], 'new_project_id' => $post['project_id']]);
            if ($res['code'] != 0) {
                rsp_die_json('10006', $res['message']);
            }
        }*/
        rsp_success_json($result['content'],'更新成功');
    }
    
    /**
     * 给员工添加岗位
     * @param $employee_id
     * @param $add_job_ids
     * @return bool
     */
    private function addEmployeeJob($employee_id, $add_job_ids)
    {
        if (empty($add_job_ids)) {
            return true;
        }
        $data = [];
        foreach ($add_job_ids as $job_id) {
            $data[] = [
                'employee_id' => $employee_id,
                'job_id' => $job_id,
            ];
        }
        $result = $this->user->post('/employeejob/batchadd', ['data' => $data]);
        if ($result['code'] != 0) {
            log_message('---Employee/'.__FUNCTION__.'---', json_encode([
                'error' => '员工添加岗位失败',
                'res' => $result
            ], JSON_UNESCAPED_UNICODE));
            return false;
        }
        return true;
    }
    
    /**
     * 删除员工的岗位
     * @param $employee_id
     * @param $del_job_ids
     * @return bool
     */
    private function delEmployeeJob($employee_id, $del_job_ids)
    {
        if (empty($del_job_ids)) {
            return true;
        }
        foreach ($del_job_ids as $job_id) {
            $result = $this->user->post('/employeejob/del', ['employee_id' => $employee_id, 'job_id' => $job_id]);
            if ($result['code'] != 0) {
                log_message('---Employee/'.__FUNCTION__.'---', json_encode([
                    'error' => '删除员工岗位失败',
                    'res' => $result
                ], JSON_UNESCAPED_UNICODE));
                return false;
            }
        }
        return true;
    }

    /**
     * @param array $post
     * @throws Exception
     * 员工添加
     */
    public function EmployeeSearch($post=[])
    {
        $check_params_info = checkEmptyParams($post,[]);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $result = $this->user->post('/employee/userlist',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json([],'查询成功');
        }
        rsp_success_json($result['content'],'查询成功');
    }
    
    /**
     * 检查岗位ID
     * @param $input_job_ids
     * @return bool
     */
    private function checkJobIds($input_job_ids)
    {
        //检查岗位
        if (is_array($input_job_ids) && !empty($input_job_ids)) {
            $job_info = $this->pm->post('/job/lists', ['job_ids' => $input_job_ids]);
            if ($job_info['code'] != 0) {
                rsp_die_json(10002, '岗位校验信息查询失败');
            }
            $job_ids = array_column($job_info['content'], 'job_id');
            $faker_job_ids = array_diff($input_job_ids, $job_ids);
            if ($faker_job_ids) {
                rsp_die_json(10001, '岗位信息或上级领导选择了非岗位数据,请检查');
            }
        }
        return true;
    }
}

