<?php

use Project\SpaceModel;

final class Client extends Base
{
    public function houseList($post=[])
    {
        log_message('xxxxx------3333-----'.json_encode($post) );
        unsetEmptyParams($post);
        $project_id = 0;
        if(isTrueKey($post,'project_id')){
            $project_id = $post['project_id'];
            unset($post['project_id']);
        }

        $app_name = ''; $project_ids = [];
        if (isTrueKey($post, 'app_name')) {
            $app_name = $post['app_name'];
            unset($post['app_name']);
        }
        $result = $this->user->post('/clienthouse/lists',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }
        $count = $this->user->post('/clienthouse/count',$post);
        if($count['code']!=0 ){
            rsp_die_json(10002,$count['message']);
        }

        if(!empty($result['content'])){
            $house_content = array_unique(array_filter(array_column($result['content'],'house_id')));
            if(!empty($house_content)){
                $house_arr =  $this->pm->post('/house/lists',['house_ids'=>$house_content]);
                $house_res = array_column($house_arr['content'],null,'house_id');
            }else{
                $house_res = [];
            }
            $cell_content = array_unique(array_filter(array_column($result['content'],'cell_id')));
            if(!empty($cell_content)){
                $cell_arr =  $this->pm->post('/house/cells/lists',['cell_ids'=>$cell_content]);
                $cell_res = array_column($cell_arr['content'],null,'cell_id');
            }else{
                $cell_res = [];
            }
            //查询租户下项目
            if ($app_name) {
                $project_ids = \Project\ProjectModel::getAppProject($app_name);
                if (!$project_ids) {
                    rsp_die_json(10002, '租户项目信息查询失败');
                }
                log_message('xxxxx------44444-----' . json_encode($post));
            }
            //如果有项目id,只响应该项目下的游客房产数据
            if ($project_id) {
                if (!empty($project_ids) && !in_array($project_id, $project_ids)) {
                    rsp_success_json(['lists' => [], 'count' => 0], '查询成功');
                }
                $project_ids = [$project_id];
            } else {
                $project_ids = array_intersect($project_ids, array_column($house_res, 'project_id'));
            }

            $project_res = $this->pm->post('/project/lists',['project_ids'=>$project_ids ]);
            if($project_res['code']!=0){
                rsp_die_json(10002,$project_res['message']);
            }
            $project_content = array_column($project_res['content'],null,'project_id');
            foreach ($result['content'] as $key => $value) {
                if (!isset($house_res[$value['house_id']])) {
                    continue;
                }
                $result['content'][$key]['space_id'] = $house_res[$value['house_id']]['space_id'] ?? '';
                $result['content'][$key]['project_id'] = $house_res[$value['house_id']]['project_id'] ?? '';
                if (isset($house_res[$value['house_id']]) && !in_array($result['content'][$key]['project_id'], $project_ids)) {
                    unset($result['content'][$key]);
                    continue;
                }
                $result['content'][$key]['space_name'] = $house_res[$value['house_id']]['space_name'] ?? '';
                $result['content'][$key]['house_floor'] = $house_res[$value['house_id']]['house_floor'] ?? '';
                $result['content'][$key]['house_unit'] = $house_res[$value['house_id']]['house_unit'] ?? '';
                $result['content'][$key]['house_room'] = $house_res[$value['house_id']]['house_room'] ?? '';
                $result['content'][$key]['cell_name'] = isset($cell_res[$value['cell_id']]) ? $cell_res[$value['cell_id']]['cell_name'] : '';
                $result['content'][$key]['project_name'] = isset($project_content[$house_res[$value['house_id']]['project_id']]) ? $project_content[$house_res[$value['house_id']]['project_id']]['project_name'] : '';
                $result['content'][$key]['support_pay'] = isset($project_content[$house_res[$value['house_id']]['project_id']]) ? $project_content[$house_res[$value['house_id']]['project_id']]['support_pay'] :'';
                //游客的房子都是未认证的
                $result['content'][$key]['client_house_status'] = 'N';

                $branch = $this->pm->post('/space/branch', ['space_id' => $result['content'][$key]['space_id']]);
                $branch = $branch['content'] ?? [];
                $branch_info = SpaceModel::parseBranch($branch, '-');
                $result['content'][$key] = array_merge($result['content'][$key], $branch_info);

            }
        }

        rsp_success_json(['lists'=>array_values($result['content']),'count'=>$count['content']],'查询成功');
    }

    public function houseAdd($post=[])
    {
        unsetEmptyParams($post);
        $check_params_info = checkEmptyParams($post, ['client_id','house_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $count = $this->user->post('/clienthouse/count',['client_id'=>$post['client_id']]);
        if($count['code']!=0 ){
            rsp_die_json(10002,$count['message']);
        }
        $config = getConfig('ms.ini');
        $limit= $config->house->limit;
        if(!empty($limit) && $count['content']>=$limit ){
            rsp_die_json(10002,'超过限制');
        }
        $show = $this->user->post('/clienthouse/lists',['client_id'=>$post['client_id'],'house_id'=>$post['house_id'],'page'=>1,'pagesize'=>1 ]);
        if($count['code']==0 && !empty($show['content']) ){
            rsp_die_json(10002,'该房子已存在，请勿重复添加');
        }
        $result = $this->user->post('/clienthouse/add',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        rsp_success_json('','添加成功');
    }

    public function houseUpdate($post=[])
    {
        unsetEmptyParams($post);
        $check_params_info = checkEmptyParams($post, ['c_house_id','client_id','house_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $result = $this->user->post('/clienthouse/update',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        rsp_success_json('','修改成功');
    }

    public function houseDel($post=[])
    {
        $check_params_info = checkEmptyParams($post, ['client_id','house_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $result = $this->user->post('/clienthouse/del',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        rsp_success_json('','删除成功');
    }

}