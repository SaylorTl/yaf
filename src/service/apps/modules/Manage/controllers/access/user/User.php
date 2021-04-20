<?php
final class User extends Base
{
    /**
     * @param array $post
     * @throws Exception
     * 员工添加
     */
    public function userAdd($post=[])
    {
        $employee_res = $this->resource->post('/resource/id/generator',['type_name'=>'employee']);
        if($employee_res['code']!=0 || ($employee_res['code']==0 && empty($employee_res['content']))){
            rsp_die_json(10001, $employee_res['message']);
        }
        $post['employee_id'] = $employee_res['content'];
        $post['creator'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
        $post['editor'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
        $result = $this->user->post('/employee/addemployee',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        rsp_success_json($result['content'],'添加成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 用户列表
     */
    public function userList($post=[])
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
        $user_type_arr = array_column($lists,'user_type_tag_id');
        $tags = array_filter(array_merge($user_type_arr));
        $tag_res = $this->tag->post('/tag/lists',['tag_ids'=>implode(',',$tags)]);
        if($tag_res['code']!=0){
            rsp_die_json(10002,$tag_res['message']);
        }
        $tag_content = array_column($tag_res['content'],null,'tag_id');
        $creator_arr = array_filter(array_unique(array_column($lists,'creator')));
        $editor_arr = array_filter(array_unique(array_column($lists,'editor')));
        $leader_arr = array_filter(array_unique(array_column($lists,'leader')));
        $creator_res = $this->user->post('/employee/lists',['employee_ids'=>array_merge($creator_arr,$editor_arr,$leader_arr)]);
        if($creator_res['code']!=0){
            rsp_die_json(10002,$creator_res['message']);
        }
        $creator_content =  array_column($creator_res['content'],null,'employee_id');
        $frame_arr = array_unique(array_filter(array_column($lists,'frame_id')));
        $frame_res = $this->pm->post('/framelists',['frame_ids'=>implode(',',$frame_arr)]);
        if($frame_res['code']!=0){
            rsp_die_json(10002,$frame_res['message']);
        }
        $frame_content = array_column($frame_res['content'],null,'frame_id');

        $employee_arr = array_filter(array_unique(array_column($lists,'employee_id')));
        $user_role_lists = $this->access->post('/role/role_lists',['employee_ids'=>$employee_arr]);
        if($user_role_lists['code']!=0){
            rsp_die_json(10002,$user_role_lists['message']);
        }
        $user_role_content = [];
        if(!empty($user_role_lists['content'])){
            foreach ($user_role_lists['content'] as $kk=>$vk){
                $user_role_content[$vk['employee_id']][] = $vk;
            }
        }
        foreach($result['content']['lists'] as $key=>$value){
            $result['content']['lists'][$key]['creator'] = isset($creator_content[$value['creator']])?$creator_content[$value['creator']]['full_name']:'';
            $result['content']['lists'][$key]['editor'] = isset($creator_content[$value['editor']])?$creator_content[$value['editor']]['full_name']:'';
            $result['content']['lists'][$key]['frame_name'] =isset($frame_content[$value['frame_id']])?$frame_content[$value['frame_id']]['frame_name']:'';
            $result['content']['lists'][$key]['leader_name'] =isset($creator_content[$value['leader']])?$creator_content[$value['leader']]['full_name']:'';
            $result['content']['lists'][$key]['user_type_tag_name'] = isset($tag_content[$value['user_type_tag_id']])?$tag_content[$value['user_type_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['role_lists'] =isset($user_role_content[$value['employee_id']])?$user_role_content[$value['employee_id']]:[];
        }
        rsp_success_json($result['content'],'查询成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 员工添加
     */
    public function userEdit($post=[])
    {
        $check_params_info = checkEmptyParams($post, ['employee_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $employee_post = [];
        $employee_post['employee_id'] = $post['employee_id'];
        $employee_post['creator'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
        $employee_post['editor'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
        $employee_post['mobile'] =!empty($post['mobile'])? $post['mobile']:'';
        $employee_post['full_name'] = !empty($post['full_name'])? $post['full_name']:'';
        $employee_post['status'] = !empty($post['status'])? $post['status']:'';
        $employee_post['user_type_tag_id'] = !empty($post['user_type_tag_id'])? $post['user_type_tag_id']:'';
        $employeeext_post['status'] = !empty($post['status'])? $post['status']:'';
        $result = $this->user->post('/employee/update',$employee_post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        $employeeext_post = [];
        $employeeext_post['employee_id'] = $post['employee_id'];
        $employeeext_post['email'] = !empty($post['email'])? $post['email']:'';
        $employeeext_post['address'] = !empty($post['address'])? $post['address']:'';
        $employeeext_post['leader'] = !empty($post['leader'])? $post['leader']:'';
        $employeeext_post['frame_id'] = !empty($post['frame_id'])? $post['frame_id']:'';
        $employeeext_post['remark'] = !empty($post['remark'])? $post['remark']:'';
        $result = $this->user->post('/employeeext/update',$employeeext_post);
        if($result['code']!=0 ){
            rsp_die_json(10003,$result['message']);
        }
        AuthEvents::updateUserStatus($post['employee_id']);
        rsp_success_json($result['content'],'编辑成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 员工添加
     */
    public function userDel($post=[])
    {
        $check_params_info = checkEmptyParams($post, ['employee_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $post['status']='N';
        $source = $this->access->post('/employee/update', $post);
        if($source['code']==0){
            rsp_success_json($source['content'],'用户删除成功');
        }
        AuthEvents::updateUserStatus($post['employee_id']);
        rsp_die_json(10004, $source['message']);
    }

    public function getUserTree($post){
        unsetEmptyParams($post);
        $check_params_info = checkEmptyParams($post, ['resource_type_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $params = ['resource_type_id' => $post['resource_type_id'],'role_id' => $post['role_id']];
        $module_res = $this->access->post('/permissions/rolePrivlegesTree',$params );
        if($module_res['code']==0){
            rsp_success_json($module_res['content'],'角色权限树查询成功');
        }
        rsp_die_json(10004, $module_res['message']);
    }

    /**
     * @param array $post
     * @throws Exception
     * 员工添加
     */
    public function userPermissionTree($post=[])
    {
        unsetEmptyParams($post);
        $check_params_info = checkEmptyParams($post, ['source_id','employee_id','resource_type_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $params = ['resource_type_id' => 10017,'source_id' => $post['source_id'],'employee_id' => $_SESSION['employee_id'],
            'role_id' => $_SESSION['member_role_id']];
        $result = $this->access->post('/permissions/userPermissionTree',$params);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        rsp_success_json($result['content'],'用户权限树查询成功');
    }

}