<?php

class AuthEvents {

    public static function updateRole($role_id){
        $key = "etbase:access_control:access_control_role:".$role_id;
        $access = new Comm_Curl([ 'service'=>'access','format'=>'json']);
        $redis = Comm_Redis::getInstance();
        $role = $access->post('/role/show', ['role_id' => $role_id]);
        if($role['code']!=0 ){
            rsp_die_json(10002,$role['message']);
        }
        if($role['code']==0 &&  empty($role['content'])){
            rsp_die_json(10002,'该账号未配置角色');
        }
        $role = $role['content'];
        $redis->setex($key,7200,json_encode($role));
    }

    public static function updateRoleAccess($role_id,$resource_type_id){
        $key = "etbase:access_control:access_control_role_access:".$role_id;
        $params = [ 'resource_type_id'=>$resource_type_id,
            'role_id'=>$role_id];
        $access = new Comm_Curl([ 'service'=>'access','format'=>'json']);
        $redis = Comm_Redis::getInstance();
        $roleresource_res = $access->post('/roleresource/lists',$params );
        if($roleresource_res['code']!=0 ){
            rsp_die_json(10002,$roleresource_res['message']);
        }
        if($roleresource_res['code']==0 &&  empty($roleresource_res['content'])){
            rsp_die_json(10002,'该角色未配置此权限');
        }
        $roleresource = $roleresource_res['content'];
        $redis->setex($key,7200,json_encode($roleresource));
    }

    public static function updateUserAccess($employee_id,$resource_type_id){
        $key = "etbase:access_control:access_control:".$employee_id;
        $params = ['resource_type_id' => $resource_type_id,'employee_id' => $employee_id];
        $access = new Comm_Curl([ 'service'=>'access','format'=>'json']);
        $redis = Comm_Redis::getInstance();
        $rolesource = $access->post('/userresource/getUserPermission',$params );
        if($rolesource['code']!=0 ){
            rsp_die_json(10002,$rolesource['message']);
        }
        $privileges = $rolesource['content'];
        $redis->setex($key,7200,json_encode($privileges));
    }

    public static function updatePermission($permissions_key){
        $key = "etbase:access_control:access_control_permission:".$permissions_key;
        $params = ['permissions_key' => $permissions_key];
        $access = new Comm_Curl([ 'service'=>'access','format'=>'json']);
        $redis = Comm_Redis::getInstance();
        $permissions_res = $access->post('/permissions/show',$params );
        if($permissions_res['code']!=0 ){
            rsp_die_json(10002,$permissions_res['message']);
        }
        if($permissions_res['code']==0 &&  empty($permissions_res['content'])){
            return [];
        }
        $permissions = $permissions_res['content'];
        $redis->setex($key,7200,json_encode($permissions));
        $route_arr = $access->post('/modulepage/lists',['ac_permissions_id' => $permissions['ac_permissions_id']] );
        if($route_arr['code']==0 && !empty($route_arr['content'])){
            foreach($route_arr['content'] as $v){
                self::updatePermissionRouteStatus($v);
            }
        }
        return true;
    }

    public static function updateRouteStatus($route_id){
        $key = "etbase:access_control:access_control_route:".$route_id;
        $params = ['path_resource_id' => $route_id];
        $access = new Comm_Curl([ 'service'=>'access','format'=>'json']);
        $redis = Comm_Redis::getInstance();
        $route_res = $access->post('/routeext/show',$params );
        if($route_res['code']!=0 ){
            rsp_die_json(10002,$route_res['message']);
        }
        if($route_res['code']==0 &&  empty($route_res['content'])){
            return [];
        }
        $route_arr = $route_res['content'];
        $redis->setex($key,7200,json_encode($route_arr));
    }

    public static function updatePermissionRouteStatus($v){
        $key = "etbase:access_control:access_control_permission_route:".$v['ac_permissions_id'].'_'.$v['path_resource_id'];
        $redis = Comm_Redis::getInstance();
        $redis->setex($key,7200,json_encode($v));
    }

    public static function updateMemberAccess($employee_id){
        $key = "etbase:access_control:access_control_user:".$employee_id;
        $user = new Comm_Curl([ 'service'=>'user','format'=>'json']);
        $redis = Comm_Redis::getInstance();
        $user_res = $user->post('/member/userlist',['employee_id'=>$employee_id]);
        if($user_res['code']!=0 || ($user_res['code']==0 && empty($user_res['content']['lists']))){
            rsp_die_json(10004, "账号信息查询失败");
        }
        $user = $user_res['content']['lists'][0];
        $redis->setex($key,7200,json_encode($user));
    }

    public static function updateSourceAccess($employee_id){
        $access = new Comm_Curl([ 'service'=>'access','format'=>'json']);
        $redis = Comm_Redis::getInstance();
        $key = "etbase:access_control:access_control_user_source:".$employee_id;
        $user_source_res = $access->post('/user/lists',['employee_id' => $employee_id] );
        if($user_source_res['code']!=0){
            rsp_die_json(10004, $user_source_res['message']);
        }
        $user_source = $user_source_res['content'];
        $redis->setex($key,7200,json_encode($user_source));
    }

    public static function updateUserStatus($employee_id){
        $key = "etbase:access_control:access_control_employee_id:".$employee_id;
        $user = new Comm_Curl([ 'service'=>'user','format'=>'json']);
        $redis = Comm_Redis::getInstance();
        $employee_res = $user->post('/employee/show',['employee_id'=>$employee_id]);
        if($employee_res['code']!=0 ){
            rsp_die_json(10002,$employee_res['message']);
        }
        $employee_source = $employee_res['content'];
        $redis->setex($key,7200,json_encode($employee_source));
    }

}
