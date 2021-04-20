<?php

class Permissions extends Base
{

    public function getPermissionsTree($post){
        unsetEmptyParams($post);
        $check_params_info = checkEmptyParams($post, ['source_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $params = ['source_id' => $post['source_id']];
        $module_res = $this->access->post('/permissions/privlegesTree',$params );
        if($module_res['code']==0){
            rsp_success_json($module_res['content'],'模块查询成功');
        }
        rsp_die_json(10004, $module_res['message']);
    }

    public function addPermissions($post){
        $check_params_info = checkEmptyParams($post, ['ac_module_id','permissions_name','permissions_key']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $route_resource_res = $this->resource->post('/resource/id/generator',['type_name'=>'access']);
        if($route_resource_res['code']!=0 || ($route_resource_res['code']==0 && empty($route_resource_res['content']))){
            rsp_die_json(10001, $route_resource_res['message']);
        }
        $post['ac_permissions_id'] = $route_resource_res['content'];
        $module_res = $this->access->post('/permissions/add',$post );
        if($module_res['code']==0){
            rsp_success_json($module_res['content'],'模块查询成功');
        }
        rsp_die_json(10004, $module_res['message']);
    }

    public function delPermissions($post){
        $check_params_info = checkEmptyParams($post, ['ac_permissions_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $module_res = $this->access->post('/permissions/delAll',['ac_permissions_id'=>$post['ac_permissions_id']] );
        if($module_res['code']!=0){
            rsp_die_json(10004, $module_res['message']);
        }
        $modulepage_res = $this->access->post('/modulepage/delPermission',['ac_permissions_id'=>$post['ac_permissions_id']] );
        if($modulepage_res['code']!=0){
            rsp_die_json(10004, $modulepage_res['message']);
        }

        rsp_success_json($modulepage_res['content'],'权限删除成功');
    }

    public function editPermissions($post){
        $check_params_info = checkEmptyParams($post, ['permissions_name','permissions_key'
            ,'is_disable','ac_module_id','permission_resoures']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        if(empty($post['ac_permissions_id'])){
            $route_resource_res = $this->resource->post('/resource/id/generator',['type_name'=>'access']);
            if($route_resource_res['code']!=0 || ($route_resource_res['code']==0 && empty($route_resource_res['content']))){
                rsp_die_json(10001, $route_resource_res['message']);
            }
            $post['ac_permissions_id'] = $route_resource_res['content'];
        }
        $permissions_params = ['permissions_key' => $post['permissions_key']];
        $permissions_show_res = $this->access->post('/permissions/show',$permissions_params );
        if($permissions_show_res['code']==0 && !empty($permissions_show_res['content'])){
            if($permissions_show_res['content']['ac_permissions_id'] != $post['ac_permissions_id']){
                rsp_die_json(10004, '该权限字段名已存在');
            }
        }
        if(!empty($post['permission_resoures'])){
            foreach($post['permission_resoures'] as $key=>$v){
                if(empty($v['ac_page_id'])){
                    $route_resource_res = $this->resource->post('/resource/id/generator',['type_name'=>'route']);
                    if($route_resource_res['code']!=0 || ($route_resource_res['code']==0 && empty($route_resource_res['content']))){
                        rsp_die_json(10001, $route_resource_res['message']);
                    }
                    $post['permission_resoures'][$key]['ac_page_id'] = $route_resource_res['content'];
                }
            }
        }
        $permissionsPage_res = $this->access->post('/permissions/permissionsPageEdit',$post );
        if($permissionsPage_res['code']!=0){
            rsp_die_json(10004, $permissionsPage_res['message']);
        }
        AuthEvents::updatePermission($post['permissions_key']);
        rsp_success_json($permissionsPage_res['content'],'权限编辑成功');
    }

    public function permissionsTree(){
        $module_res = $this->access->post('/permissions/sourcePermissionTree',[] );
        if($module_res['code']!=0){
            rsp_die_json(10004, $module_res['message']);
        }
        rsp_success_json($module_res['content'],'权限树查询成功');
    }


}