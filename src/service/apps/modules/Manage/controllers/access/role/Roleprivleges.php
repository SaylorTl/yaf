<?php
final class Roleprivleges extends Base
{

    public function addRolePrivleges($post){
        $check_params_info = checkEmptyParams($post, ['resource_type_id','role_id','resource_ids']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $params = [ 'resource_type_id'=>$post['resource_type_id'],
            'role_id'=>$post['role_id'],
            'resource_ids'=>$post['resource_ids'],];
        $roleresource_res = $this->access->post('/roleresource/batchAdd',$params );
        if($roleresource_res['code']==0){
            AuthEvents::updateRoleAccess($post['role_id'],$post['resource_type_id']);
            rsp_success_json('','权限添加成功');
        }
        rsp_die_json(10004, $roleresource_res['message']);
    }

    public function delRolePrivleges($post){
        $check_params_info = checkEmptyParams($post, ['resource_type_id','role_id','role_res_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $params = [ 'role_res_id'=>$post['role_res_id'],];
        $roleresource_res = $this->access->post('/roleresource/del',$params );
        if($roleresource_res['code']==0){
            AuthEvents::updateRoleAccess($post['role_id'],$post['resource_type_id']);
            rsp_success_json('','权限添加成功');
        }
        rsp_die_json(10004, $roleresource_res['message']);
    }

    public function getRolePrivleges($post){
        unsetEmptyParams($post);
        $check_params_info = checkEmptyParams($post, ['resource_type_id','role_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $params = [ 'resource_type_id'=>$post['resource_type_id'],
            'role_id'=>$post['role_id'],];
        $roleresource_res = $this->access->post('/roleresource/lists',$params );
        if($roleresource_res['code']!=0){
            rsp_die_json('',$roleresource_res['message']);
        }
        rsp_success_json($roleresource_res['content']);
    }


}
