<?php
class AuthModel
{
    public $access;
    public $route;
    public $user;
    public $redis;

    public function __construct()
    {
        $this->access = new Comm_Curl([ 'service'=>'access','format'=>'json']);
        $this->route = new Comm_Curl([ 'service'=>'route','format'=>'json']);
        $this->user = new Comm_Curl([ 'service'=>'user','format'=>'json']);
        $this->redis = Comm_Redis::getInstance('qx');
    }

    /**
     * 检查权限
     */
    public function checkPrivleges($post,$permission_key){
        if(empty($_SESSION['member_role_id'])){
            rsp_die_json(10001, '该账号无权限，请联系管理员');
        }
        if(empty($_SESSION['employee_id'])){
            rsp_die_json(10001, '您已退出,请重新登录');
        }
        if(empty($_SESSION['access_control_source_id'])){
            rsp_die_json(10001, '未配置子系统，请联系管理员');
        }
        $ignoreMethodMap = require(CONFIG_PATH . "/method/ignoreMethodMap.php");
        if(isset($ignoreMethodMap[$post['method']])){
            return true;
        }
        if (empty($permission_key)){
            rsp_die_json(10002, '权限字段名不存在，请联系管理员');
        }
        if(0==$_SESSION['member_p_role_id']){
            return true;
        }
        if(isTrueKey($_SERVER,'REQUEST_METHOD') && strtoupper($_SERVER['REQUEST_METHOD']) == 'OPTIONS' ){
            rsp_die_json(10002,'options预检');
        }
        if( !in_array(strtoupper($_SERVER['REQUEST_METHOD']),['GET','POST','PUT','DELETE']) ){
            rsp_die_json(10002,' 请求错误');
        }
        if(isTrueKey($post,'token') == false){
            rsp_die_json(10002,' 请求错误');
        }
        $path = $post['method'];
        $config = getConfig('ms.ini');
        $access_resource_type = $config->access->resource_type;
        $ignore_set = $config->access->ignore_set;
        if(!empty($ignore_set)){
            $ignore_arr = explode(",",$ignore_set);
            if(!in_array($_SESSION['access_control_source_id'],$ignore_arr) && $_SESSION['member_token_source']!='oa_system_scprit'){
                if(empty($_SESSION['member_project_id'])){
                    rsp_die_json(10001, '请先选择项目');
                }
            }
        }else{
            if(empty($_SESSION['member_project_id'])&& $_SESSION['member_token_source']!='oa_system_scprit'){
                rsp_die_json(10001, '请先选择项目');
            }
        }
        $pathRes = $this->route->post('/route/show', ['route_path'=>$path,'route_system_id'=>$_SESSION['access_control_source_id']]);
        if($pathRes['code']!=0 || ($pathRes['code']==0 && empty($pathRes['content']))){
            rsp_die_json(10002,'该接口未注册，请联系管理员');
        }
        $this->path_auth($pathRes['content'],$_SESSION['member_role_id'],$access_resource_type,$_SESSION['employee_id'],$ignore_set,$permission_key);
    }


    /**
     * @param $pathReource
     * @param $role_id
     * @param $resource_type_id
     * @return bool
     * 接口路由权限判断
     */
    public function path_auth($pathReource,$role_id,$resource_type_id,$employee_id,$ignore_set,$permission_key){
        $privileges = $this->getUserAccess($employee_id,$resource_type_id);
        $this->checkUserStatus($employee_id);
        $this->checkSourceAccess($employee_id);
        $this->checkRole($role_id);
        $this->checkRouteStatus($pathReource['route_id']);
        $permissions = $this->checkPermissionStatus($permission_key);
        $p_resource_res = $this->access->post('/modulepage/show', ['ac_permissions_id'=>$permissions['ac_permissions_id'],'path_resource_id'=>$pathReource['route_id']]);
        if($p_resource_res['code']!=0 || ($p_resource_res['code']==0 && empty($p_resource_res['content']))){
            rsp_die_json(10002,'该页面未配置，请联系管理员');
        }
        $privileges_lists = array_column($privileges,'resource_id');
        if(!in_array($p_resource_res['content']['ac_permissions_id'],$privileges_lists)){
            rsp_die_json(10002,'该账号无此功能权限，请联系管理员');
        }
        $privileges = array_column($privileges,null,'resource_id');
        if(!empty($ignore_set)){
            $ignore_arr = explode(",",$ignore_set);
            if(!in_array($_SESSION['access_control_source_id'],$ignore_arr)&& $_SESSION['member_token_source']!='oa_system_scprit'){
                $projects = empty($privileges[$permissions['ac_permissions_id']])?[]:$privileges[$permissions['ac_permissions_id']]['projects'];
                $projec_privileges_lists = array_column($projects,'project_resource_id');
                if(!in_array($_SESSION['member_project_id'],$projec_privileges_lists)){
                    rsp_die_json(10002,'该账号无此项目权限，请联系管理员');
                }
            }
        }else{
            $projects = empty($privileges[$permissions['ac_permissions_id']])?[]:$privileges[$permissions['ac_permissions_id']]['projects'];
            $projec_privileges_lists = array_column($projects,'project_resource_id');
            if(!in_array($_SESSION['member_project_id'],$projec_privileges_lists) && $_SESSION['member_token_source']!='oa_system_scprit'){
                rsp_die_json(10002,'该账号无此项目权限，请联系管理员');
            }
        }
        $this->checkPermissionRouteStatus($p_resource_res['content']['ac_permissions_id'],$pathReource['route_id']);
        $this->checkRoleAccess($role_id,$p_resource_res['content']['ac_permissions_id'],$resource_type_id);
        $this->checkMemberAccess(['employee_id'=>$_SESSION['employee_id']]);
    }

    public function checkRole($role_id){
        $key = "etbase:access_control:access_control_role:".$role_id;
        if( $this->redis->exists($key) ){
            $user = $this->redis->get($key);
            $role = json_decode($user,true);
        }else {
            $role = $this->access->post('/role/show', ['role_id' => $role_id]);
            if($role['code']!=0 ){
                rsp_die_json(10002,$role['message']);
            }
            if($role['code']==0 &&  empty($role['content'])){
                rsp_die_json(10002,'该账号未配置角色，请联系管理员');
            }
            $role = $role['content'];
            $this->redis->setex($key,7200,json_encode($role));
        }
        if($role['is_disable']=='Y'){
            rsp_die_json(10002,'该角色已被禁用，请联系管理员');
        }
        if($role['p_role_id']==0){
            return true;
        }
    }

    public function checkRoleAccess($role_id,$permission_id,$resource_type_id){
        $key = "etbase:access_control:access_control_role_access:".$role_id;
        if( $this->redis->exists($key) ){
            $resources = $this->redis->get($key);
            $roleresource = json_decode($resources,true);
        }else {
            $params = [ 'resource_type_id'=>$resource_type_id,
                'role_id'=>$role_id];
            $roleresource_res = $this->access->post('/roleresource/lists',$params );

            if($roleresource_res['code']!=0 ){
                rsp_die_json(10002,$roleresource_res['message']);
            }
            if($roleresource_res['code']==0 &&  empty($roleresource_res['content'])){
                rsp_die_json(10002,'该角色未配置此权限，请联系管理员');
            }
            $roleresource = $roleresource_res['content'];
            $this->redis->setex($key,7200,json_encode($roleresource));
        }
        $roleresource_lists = array_column($roleresource,'resource_id');
        if(!in_array($permission_id,$roleresource_lists)){
            rsp_die_json(10002,'该账号无此功能权限，请联系管理员');
        }
        return true;
    }

    public function checkPermissionRouteStatus($ac_permissions_id,$route_id){
        $key = "etbase:access_control:access_control_permission_route:".$ac_permissions_id.'_'.$route_id;
        if( $this->redis->exists($key) ){
            $routes = $this->redis->get($key);
            $permission_route_arr = json_decode($routes,true);
        }else {
            $params = ['ac_permissions_id'=>$ac_permissions_id,'path_resource_id' => $route_id];
            $route_res = $this->access->post('/modulepage/show',$params );
            if($route_res['code']!=0 ){
                rsp_die_json(10002,$route_res['message']);
            }
            $permission_route_arr = $route_res['content'];
            $this->redis->setex($key,7200,json_encode($permission_route_arr));
        }
        if(empty($permission_route_arr)){
            rsp_die_json(10004, "该权限接口不存在，请联系管理员");
        }
        if('Y' == $permission_route_arr['is_disable']){
            rsp_die_json(10004, "该权限接口已被禁用，请联系管理员");
        }
        return true;
    }

    /**
     * @param $route_id
     * @return array|bool
     * 检查路由状态
     */
    public function checkRouteStatus($route_id){
        $key = "etbase:access_control:access_control_route:".$route_id;
        if( $this->redis->exists($key) ){
            $routes = $this->redis->get($key);
            $route_arr = json_decode($routes,true);
        }else {
            $params = ['path_resource_id' => $route_id];
            $route_res = $this->access->post('/routeext/show',$params );
            if($route_res['code']!=0 ){
                rsp_die_json(10002,$route_res['message']);
            }
            $route_arr = $route_res['content'];
            $this->redis->setex($key,7200,json_encode($route_arr));
        }
        if(empty($route_arr)){
            rsp_die_json(10004, "该接口不存在，请联系管理员");
        }
        if('Y' == $route_arr['is_disable']){
            rsp_die_json(10004, "该接口已被禁用，请联系管理员");
        }
        return true;
    }

    /**
     * @param $permission_id
     * @return array|bool
     * 检查功能权限状态
     */
    public function checkPermissionStatus($permission_key){
        $key = "etbase:access_control:access_control_permission:".$permission_key;
        if( $this->redis->exists($key) ){
            $permissions = $this->redis->get($key);
            $permissions = json_decode($permissions,true);
        }else {
            $params = ['permissions_key' => $permission_key];
            $access_url = getConfig('ms.ini')->get('access.url');
            $permissions_res = curl_json("post", $access_url."/permissions/show", $params);
            if($permissions_res['code']!=0 ){
                rsp_die_json(10002,$permissions_res['message']);
            }
            $permissions = $permissions_res['content'];
            $this->redis->setex($key,7200,json_encode($permissions));
        }
        if(empty($permissions)){
            rsp_die_json(10004, "该权限不存在，请联系管理员");
        }
        if('Y' == $permissions['is_disable']){
            rsp_die_json(10004, "权限已被禁用，请联系管理员");
        }
        return $permissions;
    }

    /**
     * @param $role_id
     * @param $resource_type_id
     * @return array|mixed
     * 获取角色权限
     */
    public function getUserAccess($employee_id,$resource_type_id){
        $key = "etbase:access_control:access_control:".$employee_id;
        if( $this->redis->exists($key) ){
            $privileges = $this->redis->get($key);
            $privileges = json_decode($privileges,true);
        }else {
            $params = ['resource_type_id' => $resource_type_id,'employee_id' => $employee_id];
            $rolesource = $this->access->post('/userresource/getUserPermission',$params );
            if($rolesource['code']!=0 ){
                rsp_die_json(10002,$rolesource['message']);
            }
            if($rolesource['code']==0 &&  empty($rolesource['content'])){
                return [];
            }
            $privileges = $rolesource['content'];
            $this->redis->setex($key,7200,json_encode($privileges));
        }
        return $privileges;
    }

    /**
     * @param $data
     * 检查账号
     */
    public function checkMemberAccess($data){
        $key = "etbase:access_control:access_control_user:".$data['employee_id'];
        if( $this->redis->exists($key) ){
            $user = $this->redis->get($key);
            $user = json_decode($user,true);
        }else {
            $user_res = $this->user->post('/member/userlist',['employee_id'=>$data['employee_id']]);
            if($user_res['code']!=0 || ($user_res['code']==0 && empty($user_res['content']['lists']))){
                rsp_die_json(10004, "账号信息查询失败，请联系管理员");
            }
            $user = $user_res['content']['lists'][0];
            $this->redis->setex($key,7200,json_encode($user));
        }
        if($user['status'] == "N"){
            rsp_die_json(10004, "该账号已被禁用，请联系管理员");
        }
        $date = time();
        if($date>$user['end_time']){
            rsp_die_json(10004, "该账号已过期，请联系管理员");
        }
        if($date<$user['begin_time']){
            rsp_die_json(10004, "该账号未生效，请联系管理员");
        }
    }

    /**
     * @param $data
     * 检查子系统
     */
    public function checkSourceAccess($employee_id){
        $key = "etbase:access_control:access_control_user_source:".$employee_id;
        if( $this->redis->exists($key) ){
            $user = $this->redis->get($key);
            $user_source = json_decode($user,true);
        }else {
            $user_source_res = $this->access->post('/user/lists',['employee_id' => $employee_id] );
            if($user_source_res['code']!=0){
                rsp_die_json(10004, $user_source_res['message']);
            }
            $user_source = $user_source_res['content'];
            $this->redis->setex($key,7200,json_encode($user_source));
        }
        if(empty($user_source)){
            rsp_die_json(10004, "未授权系统，请联系管理员");
        }
        $user_source_lists = array_column($user_source,'source_id');
        if(!in_array($_SESSION['access_control_source_id'],$user_source_lists)){
            rsp_die_json(10002,'无此系统权限，请联系管理员');
        }
    }

    /**
     * @param $data
     * 检查子系统
     */
    public function checkUserStatus($employee_id){
        $key = "etbase:access_control:access_control_employee_id:".$employee_id;
        if( $this->redis->exists($key) ){
            $user = $this->redis->get($key);
            $user_source = json_decode($user,true);
        }else {
            $employee_res = $this->user->post('/employee/show',['employee_id'=>$employee_id]);
            if($employee_res['code']!=0 ){
                rsp_die_json(10002,$employee_res['message']);
            }
            $user_source = $employee_res['content'];
            $this->redis->setex($key,7200,json_encode($user_source));
        }
        if(empty($user_source)){
            rsp_die_json(10004, "该用户不存在，请联系管理员");
        }
        if('N' == $user_source['status']){
            rsp_die_json(10004, "该用户已被禁用，请联系管理员");
        }
    }

    /**
     * @param $permission_id
     * @return array|bool
     * 检查功能权限状态
     */
    public function getPermissionid($permission_key){
        $key = "etbase:access_control:access_control_permission:".$permission_key;
        if( $this->redis->exists($key) ){
            $permissions = $this->redis->get($key);
            $permissions = json_decode($permissions,true);
        }else {
            $params = ['permissions_key' => $permission_key];
            $access_url = getConfig('ms.ini')->get('access.url');
            $permissions_res = curl_json("post", $access_url."/permissions/show", $params);
            if($permissions_res['code']!=0 ){
                rsp_die_json(10002,$permissions_res['message']);
            }
            $permissions = $permissions_res['content'];
            $this->redis->setex($key,7200,json_encode($permissions));
        }
        return $permissions;
    }

    /**
     * @param $permission_id
     * @return array|bool
     * 检查功能权限状态
     */
    public function getPermissionProjects($permission_key,$data){
        $key = "etbase:access_control:access_control_permission:".$permission_key;
        $employee_id = $_SESSION['employee_id'];
        if( $this->redis->exists($key) ){
            $permissions = $this->redis->get($key);
            $permissions = json_decode($permissions,true);
        }else {
            $params = ['permissions_key' => $permission_key];
            $access_url = getConfig('ms.ini')->get('access.url');
            $permissions_res = curl_json("post", $access_url."/permissions/show", $params);
            if($permissions_res['code']!=0 ){
                rsp_die_json(10002,$permissions_res['message']);
            }
            $permissions = $permissions_res['content'];
            $this->redis->setex($key,7200,json_encode($permissions));
        }
        $privileges = $this->getUserAccess($employee_id,10017);
        $privileges = array_column($privileges,null,'resource_id');
        $projects = empty($privileges[$permissions['ac_permissions_id']])?[]:$privileges[$permissions['ac_permissions_id']]['projects'];
        $projects = array_column($projects,'project_resource_id');
        //超管的情况下,查all即去除project_id参数认为是查所有
        if(0 == $_SESSION['member_p_role_id'] && 'all' == $data["project_ids"]){
            unset($data["project_ids"]);
            return  $data;
        }
        //非超管的情况下，查all的时候就查出对应的项目id
        if(0 != $_SESSION['member_p_role_id'] && 'all' == $data["project_ids"]){
            $data["project_ids"] = !empty($projects)?$projects:'';
        }
        return $data;
    }

}