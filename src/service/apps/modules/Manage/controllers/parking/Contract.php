<?php

use Project\SpaceModel;

final class Contract extends Base
{
    public function generate($params = [])
    {
        unsetEmptyParams($params);
        $post = $params;
        $check_params_info = checkParams($post, ['space_id','project_id','begin_time','end_time','car_list','place_list']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $post['editor'] = $_SESSION['employee_id'];
        if(empty($post['contract_id'])){
            $post['creator'] = $_SESSION['employee_id'];
        }
        if(empty($post['house_id']) && !empty($post['mobile'])){
            $visit_res = $this->resource->post('/resource/id/generator',['type_name'=>'visitor']);
            if($visit_res['code']!=0 || ($visit_res['code']==0 && empty($visit_res['content']))){
                rsp_die_json(10001, $visit_res['message']);
            }
            if(!empty($_SESSION['member_project_id'])){
                $visitor_post['project_id'] = $_SESSION['member_project_id'];
            }
            $visitor_post['visit_id'] = $visit_res['content'];
            $visitor_post['real_name'] = $post['real_name'];
            $visitor_post['mobile'] = $post['mobile'];
            $visitor_post['creator'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
            $visitor_post['editor'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
            $result = $this->user->post('/visitor/useradd',$visitor_post);
            if($result['code']!=0 ){
                rsp_die_json(10002,$result['message']);
            }
            $visitor_res = $this->user->post('/visitor/userlist',['visit_id'=>$visitor_post['visit_id']]);
            $post['user_id'] = $visitor_res['content']['lists'][0]['user_id'];
        }
        foreach($post['car_list'] as $key=>$value){
            if(!isPlate($value['plate'])&& 1537 == $value['car_type_tag_id']){
                rsp_die_json(10001,"车牌不符合规范");
            }
            $car_add_res = $this->car->post('/id',$value);
            if(0 != $car_add_res['code'] || (0== $car_add_res['code'] && empty($car_add_res['content']))){
                rsp_die_json(10001,"车牌不符合规范");
            }
            $post['car_list'][$key]['car_id'] = $car_add_res['content'];
        }
        $contract_add_res = $this->contract->post('/contract/generate',$post);
        if(0 != $contract_add_res['code']){
            rsp_die_json(10001,$contract_add_res['message']);
        }
        if(empty($post['contract_id'])){
            rsp_success_json($contract_add_res['content'],'添加成功');
        }
        rsp_success_json('','更新成功');
    }

    public function lists($params = [])
    {
        unsetEmptyParams($params);
        $post = $params;
        if(!empty($_SESSION['member_project_id'])){
            $post['project_id'] = $_SESSION['member_project_id'];
        }
        if(!empty($post['plate'])){
            $post['car_id'] = encode_plate($params['plate']);
            unset($post['plate']);
        }
        $contract_show_res = $this->contract->post('/contract/joinList',$post);
        if(0 != $contract_show_res['code']){
            rsp_die_json(10001,$contract_show_res['message']);
        }
        $project_arr = array_unique(array_filter(array_column($contract_show_res['content'],'project_id')));
        $project_res = $this->pm->post('/project/lists',['project_ids'=>$project_arr]);
        if($project_res['code']!=0){
            rsp_die_json(10002,$project_res['message']);
        }
        $project_content = array_column($project_res['content'],null,'project_id');

        $creator_arr = array_filter(array_unique(array_column($contract_show_res['content'],'creator')));
        $editor_arr = array_filter(array_unique(array_column($contract_show_res['content'],'editor')));
        $creator_res = $this->user->post('/employee/lists',['employee_ids'=>array_merge($creator_arr,$editor_arr)]);
        if($creator_res['code']!=0){
            rsp_die_json(10002,$creator_res['message']);
        }
        $creator_content =  array_column($creator_res['content'],null,'employee_id');

//        $house_content = array_unique(array_filter(array_column($contract_show_res['content'],'house_id')));
//        if(!empty($house_content)){
//            $house_arr =  $this->pm->post('/house/lists',['house_ids'=>$house_content]);
//            $house_res = array_column($house_arr['content'],null,'house_id');
//        }

        $space_ids = array_unique(array_filter(array_column($contract_show_res['content'],'space_id')));
        $space_branches = $this->pm->post('/space/batchBranch', ['space_ids' => $space_ids]);
        $space_branches = $space_branches['content'] ?? [];

        $user_ids = array_filter(array_unique(array_column($contract_show_res['content'],'user_id')));
        $user_Res = $this->user->post('/tenement/userlist',['project_id'=>$_SESSION['member_project_id'],'user_ids'=>$user_ids]);
        if($user_Res['code']!=0 ){
            rsp_die_json(10002,$user_Res['message']);
        }
        $user_arr =  [];
        if(!empty($user_Res['content']) ){
            $user_arr = $user_Res['content']['lists'];
        }
        $user_content = array_column($user_arr,null,'user_id');
        foreach($contract_show_res['content'] as $key=>$value){
            $contract_show_res['content'][$key]['project_name'] =isset($project_content[$value['project_id']])?$project_content[$value['project_id']]['project_name']:'';
            $contract_show_res['content'][$key]['end_time'] = date('Y-m-d H:i:s',$value['end_time']);
            $contract_show_res['content'][$key]['begin_time'] = date('Y-m-d H:i:s',$value['begin_time']);
            $contract_show_res['content'][$key]['creator'] = isset($creator_content[$value['creator']])?$creator_content[$value['creator']]['full_name']:'';
            $contract_show_res['content'][$key]['editor'] = isset($creator_content[$value['editor']])?$creator_content[$value['editor']]['full_name']:'';
            $contract_show_res['content'][$key]['real_name'] = isset($value['house_id'])&& !empty($user_content[$value['user_id']])?$user_content[$value['user_id']]['real_name']:$value['real_name'];
            $contract_show_res['content'][$key]['mobile'] = isset($value['house_id'])&& !empty($user_content[$value['user_id']])?$user_content[$value['user_id']]['mobile']:$value['mobile'];
            $branch_info = SpaceModel::parseBranch($space_branches[$value['space_id']] ?? []);
            $contract_show_res['content'][$key]['space_name_full'] = $branch_info['space_name_full'] ?? '';
        }
        $contract_count_res = $this->contract->post('/contract/joinCount',$post);
        if(0 != $contract_count_res['code']){
            rsp_die_json(10001,$contract_count_res['message']);
        }
        rsp_success_json(['total' => (int)$contract_count_res['content'], 'lists' =>$contract_show_res['content']],'查询成功');
    }

    public function extList($params = [])
    {
        unsetEmptyParams($params);
        $post = $params;
        $result = $this->contract->post('/contract/extList',$post);
        if(0 != $result['code']){
            rsp_die_json(10001,$result['message']);
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json(['car_list'=>[],'place_list'=>[]],'查询成功');
        }

        if(!empty($result['content']['car_list'])){
            $car_ids = array_column($result['content']['car_list'],'car_id');
            $car_res = $this->car->post('/car/lists',['ids'=>$car_ids]);
            if(0 != $car_res['code']||$car_res['code']==0 &&  empty($car_res['content'])){
                rsp_die_json(10001,'车辆查询失败');
            }
            $car_arr = array_column($car_res['content'],null,'id');
            $car_type_arr = array_column($result['content']['car_list'],'car_type_tag_id');
            $plate_color_arr = array_column($result['content']['car_list'],'plate_color_tag_id');
            $car_color_arr = array_column($result['content']['car_list'],'car_color_tag_id');
            $tags = array_unique(array_merge($car_type_arr,$plate_color_arr,$car_color_arr));
            $tag_res = $this->tag->post('/tag/lists',['tag_ids'=>implode(',',$tags)]);
            if($tag_res['code']!=0){
                rsp_die_json(10002,$tag_res['message']);
            }
            $tag_content = array_column($tag_res['content'],null,'tag_id');
            if(!empty($result['content']['car_list'])){
                foreach($result['content']['car_list'] as $key=>$value){
                    $result['content']['car_list'][$key] = $car_arr[$value['car_id']];
                    $value = $car_arr[$value['car_id']];
                    $result['content']['car_list'][$key]['car_type_tag_name'] = isset($tag_content[$value['car_type_tag_id']])?$tag_content[$value['car_type_tag_id']]['tag_name']:'';
                    $result['content']['car_list'][$key]['car_color_tag_name'] = isset($tag_content[$value['car_color_tag_id']])?$tag_content[$value['car_color_tag_id']]['tag_name']:'';
                    $result['content']['car_list'][$key]['plate_color_tag_name'] = isset($tag_content[$value['plate_color_tag_id']])?$tag_content[$value['plate_color_tag_id']]['tag_name']:'';
                }
            }
        }

        if(!empty($result['content']['place_list'])){
            $place_ids = array_column($result['content']['place_list'],'place_id');
            $place_res = $this->pm->post('/parkplace/lists',['place_ids'=>$place_ids]);
            if(0 != $place_res['code']||$place_res['code']==0 &&  empty($place_res['content'])){
                rsp_die_json(10001,'车位查询失败');
            }
            $place_arr = array_column($place_res['content'],null,'place_id');
            $place_type_arr = array_column($result['content']['place_list'],'place_type');
            $tag_res = $this->tag->post('/tag/lists',['tag_ids'=>implode(',',$place_type_arr)]);
            if($tag_res['code']!=0){
                rsp_die_json(10002,$tag_res['message']);
            }
            $tag_content = array_column($tag_res['content'],null,'tag_id');

            if(!empty($result['content']['place_list'])){
                foreach($result['content']['place_list'] as $k=>$v){
                    $result['content']['place_list'][$k] = $place_arr[$v['place_id']];
                    $result['content']['place_list'][$k]['rule_name'] = $v['rule_name'] ?? '';
                    $v = $place_arr[$v['place_id']];
                    $result['content']['place_list'][$k]['place_type_tag_name'] = isset($tag_content[$v['place_type']])?$tag_content[$v['place_type']]['tag_name']:'';
                }
            }
        }
        rsp_success_json($result['content'],'查询成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 根据用户id | 手机号码 查询所有房产信息
     */
    public function houseLists(){
        if(empty($_SESSION['member_project_id'])){
            rsp_die_json(10001, '参数缺失');
        }
        $user_data = ['page'=>0,'pagesize'=>0];
        if(!empty($_SESSION['member_project_id'])){
            $user_data['project_id'] = $_SESSION['member_project_id'];
        }
        $result = $this->user->post('/house/tenementlists',$user_data);
        log_message('----user_house_lists1----'.json_encode($result));
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }

        $house_ids = array_unique(array_column($result['content'], 'house_id'));
        $houses = $this->pm->post('/house/lists',['house_ids'=>$house_ids]);
        log_message('----user_house_lists3----'.json_encode($houses));
        if($houses['code'] != 0){
            rsp_die_json(10004,$houses['message']);
        }
        $houses = array_map(function ($m) {
            $branch = $this->pm->post('/space/branch', ['space_id' => $m['space_id']]);
            $branch = $branch['content'] ?? [];
            $branch_info = SpaceModel::parseBranch($branch);
            $m = array_merge($m, $branch_info);
            return $m;
        }, $houses['content'] ?? []);

        rsp_success_json(['lists' => $houses, 'count' => count($houses)]);
    }

    /**
     * @param array $post
     * @throws Exception
     * 业主列表
     */
    public function TenementList($post=[])
    {
        unsetEmptyParams($post);
        if(empty($post['house_id'])){
            rsp_die_json(10001, '参数缺失');
        }
        $houseParams = [];
        if(isset($post['house_id'])){
            $houseParams['house_ids'][] = $post['house_id'];
            unset($post['house_id']);
        }
        $result = $this->user->post('/tenement/userlist',$houseParams);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }
        rsp_success_json($result['content'],'查询成功');
    }

    public function sizeList()
    {
        $car_res = $this->car->post('/car/size/lists',[]);
        if($car_res['code']!=0 ){
            rsp_die_json(10002,'查询失败');
        }
        rsp_success_json($car_res['content'],'查询成功');
    }


}