<?php

class Useraccess extends Base
{

    public function getUserPermission($post){
        unsetEmptyParams($post);
        $check_params_info = checkEmptyParams($post, ['resource_type_id','employee_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $params = ['resource_type_id' => $post['resource_type_id'],'employee_id' => $post['employee_id']];
        $module_res = $this->access->post('/userresource/getUserPermission',$params );
        if($module_res['code']==0){
            rsp_success_json($module_res['content'],'用户权限查询成功');
        }
        rsp_die_json(10004, $module_res['message']);
    }

    public function editUserPermission($post){
        unsetEmptyParams($post);
        $check_params_info = checkEmptyParams($post, ['permissionArr','role_id','employee_id','resource_type_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $this->setMemberPrivleges($post);
        $params = ['permissionArr' => $post['permissionArr'],'employee_id' => $post['employee_id'],'resource_type_id' => $post['resource_type_id']];
        $module_res = $this->access->post('/userresource/editUserPermission',$params );
        if($module_res['code']==0){
            AuthEvents::updateUserAccess($post['employee_id'],$post['resource_type_id']);
            $cfg = getConfig('ms.ini');
            $oauthUrl = $cfg->auth2->url ?? '';
            curl_json("post",$oauthUrl."/redis/delete/authUser",['access_token'=>session_id(),'employee_id'=>$post['employee_id']]);
            rsp_success_json($module_res['content'],'用户权限编辑成功');
        }
        rsp_die_json(10004, $module_res['message']);
    }



    public function setMemberPrivleges($post){
        $role_res = $this->access->post('/userrole/show',['employee_id'=>$post['employee_id']] );
        if($role_res['code']!=0){
            rsp_die_json(10004, '查询账号角色失败');
        }
        if(empty($role_res['content'])){
            $params = ['role_id'=>$post['role_id'],
                'employee_id'=>$post['employee_id']];
            $roleresource_add_res = $this->access->post('/userrole/add',$params );
            if($roleresource_add_res['code']!=0){
                rsp_die_json(10004, $roleresource_add_res['message']);
            }
        }else{
            $params = ['role_id'=>$post['role_id'],
                'employee_id'=>$post['employee_id']];
            $roleresource_update_res = $this->access->post('/userrole/update',$params );
            if($roleresource_update_res['code']!=0){
                rsp_die_json(10004, $roleresource_update_res['message']);
            }
        }
        return true;
    }

}