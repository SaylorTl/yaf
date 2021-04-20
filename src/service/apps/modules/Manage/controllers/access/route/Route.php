<?php
final class Route extends Base
{
    /**
     * @param $post
     * 添加路由
     */
    public function addRoute($post){
        $check_params_info = checkEmptyParams($post, ['source_id','route_path','route_cname','route_type_tag_id'
            ,'route_method_tag_id','route_status_tag_id','route_version']);
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
            'route_name'=>$post['route_name'],
            'route_cname'=>$post['route_cname'],
            'route_type_tag_id'=>$post['route_type_tag_id'],
            'route_method_tag_id'=>$post['route_method_tag_id'],
            'route_status_tag_id'=>$post['route_status_tag_id'],
            'route_version'=>$post['route_version']];
        $route_res = $this->route->post('/route/add',$params);
        if($route_res['code']===0){
            rsp_success_json('','接口添加成功');
        }
        rsp_die_json(10004, $route_res['message']);
    }

    /**
     * @param $post
     * 批量添加路由
     */
    public function batchAddRoute($post){
        $check_params_info = checkEmptyParams($post, ['routeArr']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $params = [];
        $route_params = [];
        foreach($post['routeArr'] as $key=>$value){
            $check_params_info = checkEmptyParams($value, ['source_id','resource_type_id','route_version',
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
            $params[] = ['route_id' => $route_id,
                'route_system_id'=>$value['source_id'],
                'route_path'=>empty($value['route_path'])?'':$value['route_path'],
                'route_cname'=>empty($value['route_cname'])?'':$value['route_cname'],
                'route_name'=>empty($value['route_name'])?'':$value['route_name'],
                'route_type_tag_id'=>empty($value['route_type_tag_id'])?'':$value['route_type_tag_id'],
                'route_method_tag_id'=>empty($value['route_method_tag_id'])?'':$value['route_method_tag_id'],
                'route_status_tag_id'=>empty($value['route_status_tag_id'])?'':$value['route_status_tag_id'],
                'route_remark'=>empty($value['route_remark'])?'':$value['route_remark'],
                'route_version'=>empty($value['route_version'])?'':$value['route_version']
            ];
            $route_params []= ['path_resource_id'=>$route_id,
                'source_id'=>$value['source_id'],
                'is_disable'=>empty($value['is_disable'])?'N':$value['is_disable'],
            ];
        }
        $route_res = $this->route->post('/route/bulkAdd',['data'=>json_encode($params,true)]);
        if($route_res['code']!=0 || empty($route_res)){
            rsp_die_json(10001, $route_res['message']);
        }
        $module_page_res = $this->access->post('/routeext/batchAdd',['route_data'=>$route_params]);
        if($module_page_res['code']!=0){
            rsp_die_json(10004, $module_page_res['message']);
        }
        rsp_success_json($module_page_res['content'],'接口添加成功');
    }

    /**
     * @param $post
     * 路由列表
     */
    public function routeList($post){
        unsetEmptyParams($post);
        $check_params_info = checkEmptyParams($post, ['resource_type_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $page = empty($post['page'])?1:$post['page'];
        $pagesize = empty($post['pagesize'])?20:$post['pagesize'];
        $params = [];
        if(!empty($post['source_id'])){
            $params['source_id'] = $post['source_id'];
        }
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
        $routeextParams = $params;
        $routeextParams['page'] = 0;
        $routeextParams['pagesize'] = 0;
        $module_res = $this->access->post('/routeext/lists', $routeextParams);
        if($module_res['code']!=0){
            rsp_die_json(10004, $module_res['message']);
        }
        if($module_res['code']==0 &&  empty($module_res['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }
        unset($params['pagesize']);
        unset($params['page']);
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
        if($route_res['code']==0 &&  empty($route_res['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }
        $count_res = $this->route->post('/route/count',$route_params);
        if($count_res['code']!=0){
            rsp_die_json(10001, $count_res['message']);
        }
        if($route_res['code']==0 &&  empty($route_res['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }
        foreach($route_res['content'] as $key=>$value){
            if(!empty($value)){
                $route_res['content'][$key]['is_disable'] = $page_content[$value['route_id']]['is_disable'];
                $route_res['content'][$key]['path_resource_id'] = $page_content[$value['route_id']]['path_resource_id'];
                $route_res['content'][$key]['create_at'] = $page_content[$value['route_id']]['create_at'];
                $route_res['content'][$key]['update_at'] = $page_content[$value['route_id']]['update_at'];
                $route_res['content'][$key]['source_id'] = $page_content[$value['route_id']]['source_id'];
            }
        }
        rsp_success_json(['lists'=>$route_res['content'],'count'=>$count_res['content']],'接口查询成功');
    }

}