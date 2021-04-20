<?php
class Privleges extends Base
{

    public function getPrivlegesTree($post){
        unsetEmptyParams($post);
        $check_params_info = checkEmptyParams($post, ['resource_type_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $params = ['resource_type_id' => $post['resource_type_id']];
        $module_res = $this->access->post('/modulepage/permissionTree',$params );
        if($module_res['code']==0){
            rsp_success_json($module_res['content'],'权限查询成功');
        }
        rsp_die_json(10004, $module_res['message']);
    }



    /**
     * @param $post
     * 获取权限资源树
     */
    public function getPrivlegesLists($post){
        unsetEmptyParams($post);
        $check_params_info = checkEmptyParams($post, ['source_id','resource_type_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $page = empty($P['page'])?1:$P['page'];
        $pagesize = empty($P['pagesize'])?20:$P['pagesize'];
        $params = ['source_id' => $post['source_id'],
            'resource_type_id' => $post['resource_type_id'],
        ];
        if(!empty($post['ac_permissions_id'])){
            $params['ac_permissions_id'] = $post['ac_permissions_id'];
        }
        if(!empty($post['create_begin_time'])){
            $params['create_begin_time'] = $post['create_begin_time'];
        }
        if(!empty($post['create_end_time'])){
            $params['create_end_time'] = $post['create_end_time'];
        }
        if(!empty($post['is_disable'])){
            $params['is_disable'] = $post['is_disable'];
        }
        if(!empty($post['p_resource_ids'])){
            $params['p_resource_ids'] = $post['p_resource_ids'];
        }
        $module_res = $this->access->post('/modulepage/modulePrivlegesList',$params );
        if($module_res['code']!=0){
            rsp_die_json(10004, $module_res['message']);
        }
        if($module_res['code']==0 && empty($module_res['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'路由查询成功');
        }
        $page_content = array_column($module_res['content'],null,'path_resource_id');
        $route_arr = array_column($module_res['content'],'path_resource_id');
        $route_params = ['route_ids'=>$route_arr,'page'=>$page, 'pagesize'=>$pagesize];
        if(!empty($post['route_cname'])){
            $route_params['route_cname'] = $post['route_cname'];
        }
        if(!empty($post['route_path'])){
            $route_params['route_path'] = $post['route_path'];
        }
        $route_res = $this->route->post('/route/lists',$route_params);
        if($route_res['code']!=0){
            rsp_die_json(10001, $route_res['message']);
        }
        if($route_res['code']==0 && empty($route_res['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'路由查询成功');
        }
        foreach($route_res['content'] as $key=>$value){
            if(!empty($value)){
                $route_res['content'][$key]['ac_page_id'] = $page_content[$value['route_id']]['ac_page_id'];
                $route_res['content'][$key]['resource_type_id'] = $page_content[$value['route_id']]['resource_type_id'];
                $route_res['content'][$key]['p_resource_id'] = $page_content[$value['route_id']]['p_resource_id'];
                $route_res['content'][$key]['is_disable'] = $page_content[$value['route_id']]['is_disable'];
                $route_res['content'][$key]['path_resource_id'] = $page_content[$value['route_id']]['path_resource_id'];
                $route_res['content'][$key]['module_lists'] = $page_content[$value['route_id']]['module_lists'];
                $route_res['content'][$key]['create_at'] = $page_content[$value['route_id']]['create_at'];
                $route_res['content'][$key]['update_at'] = $page_content[$value['route_id']]['update_at'];
            }
        }
        $count_res = $this->route->post('/route/count',$route_params);
        if($count_res['code']!=0){
            rsp_die_json(10001, $count_res['message']);
        }
        rsp_success_json(['lists'=>$route_res['content'],'count'=>$count_res['content']],'路由查询成功');
    }

    /**
     * @param $post
     * 添加模块资源
     */
    public function addPrivlegesPath($post){
        $check_params_info = checkParams($post, ['source_id','resource_type_id','route_version',
            'route_path','route_cname','route_type_tag_id','route_method_tag_id','route_status_tag_id',
        ]);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $route_resource_res = $this->resource->post('/resource/id/generator',['type_name'=>'route']);
        if($route_resource_res['code']!=0 || ($route_resource_res['code']==0 && empty($route_resource_res['content']))){
            rsp_die_json(10001, $route_resource_res['message']);
        }
        $route_id = $route_resource_res['content'];
        $params = ['route_id' => $route_id,
            'route_system_id'=>$post['source_id'],
            'route_path'=>$post['route_path'],
            'route_cname'=>$post['route_cname'],
            'route_name'=>empty($post['route_name'])?'':$post['route_name'],
            'route_type_tag_id'=>empty($post['route_type_tag_id'])?'':$post['route_type_tag_id'],
            'route_method_tag_id'=>empty($post['route_method_tag_id'])?'':$post['route_method_tag_id'],
            'route_status_tag_id'=>empty($post['route_status_tag_id'])?'':$post['route_status_tag_id'],
            'route_remark'=>empty($post['route_remark'])?'':$post['route_remark'],
            'route_version'=>empty($post['route_version'])?'':$post['route_version']];
        $route_res = $this->route->post('/route/add',$params);
        if($route_res['code']!=0){
            rsp_die_json(10001, $route_res['message']);
        }
        $module_page_res = $this->access->post('/routeext/add', ['path_resource_id'=>$route_id,
            'source_id'=>$post['source_id'],
            'is_disable'=>empty($post['is_disable'])?'N':$post['is_disable'],
        ]);
        if($module_page_res['code']==0){
            rsp_success_json($module_page_res['content'],'模块添加成功');
        }
        rsp_die_json(10004, $module_page_res['message']);
    }



    public function updatePrivlegesPath($post){
        $check_params_info = checkParams($post, ['source_id','path_resource_id','resource_type_id','route_version',
            'route_path','route_cname','route_type_tag_id','route_method_tag_id','route_status_tag_id',
        ]);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $params = ['route_id' => $post['path_resource_id'],
            'route_system_id'=>$post['source_id'],
            'route_path'=>empty($post['route_path'])?'':$post['route_path'],
            'route_cname'=>empty($post['route_cname'])?'':$post['route_cname'],
            'route_name'=>empty($post['route_name'])?'':$post['route_name'],
            'route_type_tag_id'=>empty($post['route_type_tag_id'])?'':$post['route_type_tag_id'],
            'route_method_tag_id'=>empty($post['route_method_tag_id'])?'':$post['route_method_tag_id'],
            'route_status_tag_id'=>empty($post['route_status_tag_id'])?'':$post['route_status_tag_id'],
            'route_remark'=>empty($post['route_remark'])?'':$post['route_remark'],
            'route_version'=>empty($post['route_version'])?'':$post['route_version']];
        $route_res = $this->route->post('/route/update',$params);
        if($route_res['code']!=0){
            rsp_die_json(10001, $route_res['message']);
        }
        $updateParams = ['path_resource_id'=>$post['path_resource_id'],
            'source_id'=>$post['source_id'],
        ];
        if(!empty($post['is_disable'])){
            $updateParams['is_disable'] = $post['is_disable'];
        }
        $module_page_res = $this->access->post('/routeext/update', $updateParams);
        if($module_page_res['code']==0){
            AuthEvents::updateRouteStatus($post['path_resource_id']);
            rsp_success_json($module_page_res['content'],'接口更新成功');
        }
        rsp_die_json(10004, $module_page_res['message']);
    }


    public function delPrivlegesPath($post){
        $check_params_info = checkEmptyParams($post, ['path_resource_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $route_res = $this->route->post('/route/delete',['route_ids'=>[$post['path_resource_id']]]);
        if($route_res['code']!=0){
            rsp_die_json(10001, $route_res['message']);
        }
        $module_page_res = $this->access->post('/routeext/del', ['path_resource_id'=>$post['path_resource_id'],]);
        if($module_page_res['code']==0){
            AuthEvents::updateRouteStatus($post['path_resource_id']);
            rsp_success_json($module_page_res['content'],'路由删除成功');
        }
        rsp_die_json(10004, $module_page_res['message']);
    }

    public function getPrivlegesPath($post){
        $check_params_info = checkEmptyParams($post, ['employee_id','resource_type_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $role_res = $this->access->post('/modulepage/projectPageLists', ['employee_id' => $post['employee_id'],
            'resource_type_id'=>$post['resource_type_id']]);
        if($role_res['code']!=0){
            rsp_die_json(10002,$role_res['message']);
        }
        $role_resource = $role_res['content'];
        $path_ids = array_column($role_resource,'path_resource_id');
        $routhRes = $this->route->post('/route/lists', ['route_ids'=>$path_ids]);
        if($routhRes['code']!=0){
            rsp_die_json(10002,$routhRes['message']);
        }
        rsp_success_json($routhRes['content'],'查询成功');
    }
}
