<?php

final class Module extends Base
{
    public function getPermissionProjects($post){
        $check_params_info = checkEmptyParams($post, ['employee_id','page','pagesize','resource_type_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $permission_key = !empty($_GET['permissions_key'])?$_GET['permissions_key']:'';
        if (empty($permission_key)) {
            rsp_die_json(10001,  'permission_key不存在');
        }
        $projectParams = ['page'=>$post['page'],'pagesize'=>$post['pagesize']];
        if(0!=$_SESSION['member_p_role_id']){
            $permission_res = $this->access->post('/permissions/show',['permissions_key' => $permission_key] );
            if($permission_res['code']!=0 || ($permission_res['code']==0 && empty($permission_res['content']))){
                rsp_die_json(10002,'该权限不存在');
            }
            $permission_id = $permission_res['content']['ac_permissions_id'];
            $user_resource_res = $this->access->post('/userresource/lists', ['employee_id' => $post['employee_id'],
                'resource_id'=>$permission_id,'resource_type_id'=>$post['resource_type_id']]);
            if($user_resource_res['code']!=0){
                rsp_die_json(10002,$user_resource_res['message']);
            }
            if($user_resource_res['code']==0 && empty($user_resource_res['content'])){
                rsp_success_json([],'查询成功');
            }
            $user_resource = $user_resource_res['content'];
            $user_res_ids = array_column($user_resource,'user_res_id');
            $projects_res = $this->access->post('/permissionproject/lists', ['user_res_ids' => $user_res_ids]);
            if($projects_res['code']!=0){
                rsp_die_json(10002,$projects_res['message']);
            }
            if($projects_res['code']==0 && empty($projects_res['content'])){
                rsp_success_json([],'查询成功');
            }
            $project_source_ids = array_unique(array_column($projects_res['content'],'project_resource_id'));
            $projectParams['project_ids'] = $project_source_ids;
        }
        if(!empty($post['project_name'])){
            $projectParams['project_name'] = $post['project_name'];
        }
        $pm_list = $this->pm->post('/project/projects',$projectParams);
        if($pm_list['code']!=0){
            rsp_die_json(10002,'项目查询失败');
        }
        if($pm_list['code']==0 && empty($pm_list['content'])){
            rsp_success_json([],'查询成功');
        }
        rsp_success_json($pm_list['content'],'查询成功');
    }

    public function getProjectList($post){
        $check_params_info = checkEmptyParams($post, ['employee_id','page','pagesize','resource_type_id','source_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $role_res = $this->access->post('/user/detail', ['employee_id' => $post['employee_id']]);
        if($role_res['code']!=0){
            rsp_die_json(10002,$role_res['message']);
        }
        if($role_res['code']==0 &&  empty($role_res['content'])){
            rsp_die_json(10002,'该账号无权限');
        }
        $role = $role_res['content'];
        if($role['p_role_id']==0){
            $projectParams = ['page'=>$post['page'],'pagesize'=>$post['pagesize']];
            if(!empty($post['project_name'])){
                $projectParams['project_name'] = $post['project_name'];
            }
            $pm_list = $this->pm->post('/project/projects',$projectParams);
        }else{
             $module_lists_res = $this->access->post('/module/lists', ['source_id' => $post['source_id']]);
            if($module_lists_res['code']!=0){
                rsp_die_json(10002,$module_lists_res['message']);
            }
            $module_lists_arr= array_column($module_lists_res['content'],'ac_module_id');

            $permission_lists_res = $this->access->post('/permissions/lists', ['ac_module_ids' => $module_lists_arr]);
            if($permission_lists_res['code']!=0){
                rsp_die_json(10002,$permission_lists_res['message']);
            }
            $permission_lists_arr= array_column($permission_lists_res['content'],'ac_permissions_id');

            $user_resource_res = $this->access->post('/userresource/lists', ['employee_id' => $post['employee_id'],
                'resource_type_id'=>$post['resource_type_id'],'resource_ids'=>$permission_lists_arr]);
            if($user_resource_res['code']!=0){
                rsp_die_json(10002,$user_resource_res['message']);
            }
            if($user_resource_res['code']==0 && empty($user_resource_res['content'])){
                rsp_success_json([],'查询成功');
            }
            $user_resource = $user_resource_res['content'];
            $user_res_ids = array_column($user_resource,'user_res_id');
            $projects_res = $this->access->post('/permissionproject/lists', ['user_res_ids' => $user_res_ids]);
            if($projects_res['code']!=0){
                rsp_die_json(10002,$projects_res['message']);
            }
            if($projects_res['code']==0 && empty($projects_res['content'])){
                rsp_success_json([],'查询成功');
            }
            $project_source_ids = array_unique(array_column($projects_res['content'],'project_resource_id'));
            $projectParams = ['project_ids'=>$project_source_ids,'page'=>$post['page'],'pagesize'=>$post['pagesize']];
            if(!empty($post['project_name'])){
                $projectParams['project_name'] = $post['project_name'];
            }
            $pm_list = $this->pm->post('/project/projects',$projectParams);
        }
        if($pm_list['code']!=0){
            rsp_die_json(10002,'项目查询失败');
        }
        if($pm_list['code']==0 && empty($pm_list['content'])){
            rsp_success_json([],'查询成功');
        }
        rsp_success_json($pm_list['content'],'查询成功');
    }

    public function changeProject($post){
        $check_params_info = checkEmptyParams($post, ['employee_id','project_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $role_res = $this->user->post('/member/update', ['employee_id' => $post['employee_id'],
            'last_login_project_id'=>$post['project_id']]);
        if($role_res['code']!=0){
            rsp_die_json(10002,$role_res['message']);
        }
        $_SESSION['member_project_id'] = $post['project_id'];
        $cfg = getConfig('ms.ini');
        $oauthUrl = $cfg->auth2->url ?? '';
        curl_json("post",$oauthUrl."/redis/delete/authUser",['access_token'=>session_id(),'employee_id'=>$post['employee_id']]);
        rsp_success_json('','切换成功');
    }

    public function moduleTree($post){
        unsetEmptyParams($post);
        $check_params_info = checkEmptyParams($post, ['resource_type_id','source_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $params = ['resource_type_id' => $post['resource_type_id'],'source_id'=>$post['source_id']];
        $module_res = $this->access->post('/module/moduleTree',$params );
        if($module_res['code']==0){
            rsp_success_json($module_res['content'],'权限树查询成功');
        }
        rsp_die_json(10004, $module_res['message']);
    }
}