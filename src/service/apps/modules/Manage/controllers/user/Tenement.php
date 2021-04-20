<?php

use Project\SpaceModel;

final class Tenement extends Base
{

    /**
     * @param array $post
     * @throws Exception
     * 业主列表
     */
    public function TenementList($post=[])
    {
        unsetEmptyParams($post);
        $houseParams = [];
        if (isTrueKey($post, 'space_id')) {
            $space_ids = $this->pm->post('/space/children', ['space_id' => $post['space_id']]);
            $space_ids = ($space_ids['code'] === 0 && $space_ids['content']) ? $space_ids['content'] : [];
            if (!$space_ids) rsp_success_json(['total' => 0, 'lists' => []]);
            unset($post['space_id']);
            $houseParams['space_ids'] = array_unique(array_filter(array_column($space_ids, 'space_id')));
        }
        if(isset($post['house_id'])){
            $houseParams['house_id'] = $post['house_id'];
            unset($post['house_id']);
        }
        if(!empty($houseParams)){
            $houseParams['is_paging'] = 'N';
            $houseRes = $this->pm->post('/house/lists',$houseParams);
            if ($houseRes['code'] !== 0){
                rsp_die_json(10002,$houseRes['message']);
            }
            if (empty($houseRes['content'])){
                rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
            }
            $house_ids = array_column($houseRes['content'],'house_id');
            $post['house_ids'] = $house_ids;
        }
        $cellParams = [];
        if(!empty($post['cell_name'])){
            $cellParams['cell_name'] = $post['cell_name'];
            unset($post['cell_name']);
            if(!empty($house_ids)){
                $cellParams['house_ids'] = $house_ids;
            }
            $cellRes = $this->pm->post('/house/cells/lists',$cellParams);
            if ($cellRes['code'] !== 0){
                rsp_die_json(10002,$cellRes['message']);
            }
            if (empty($cellRes['content'])){
                rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
            }
            $cell_ids = array_column($cellRes['content'],'cell_id');
            $post['cell_ids'] = $cell_ids;
        }

        if (empty($post['project_id']) && !empty($_SESSION['member_project_id'])) {
            $post['project_id'] = $_SESSION['member_project_id'];
        }
        if( (isset($post['project_ids']) && $post['project_ids'] == 'all')){
            unset($post['project_id']);
        }
        $tenement_ids = $this->getTenementIds($post);
        if( $tenement_ids ){
            $post['tenement_ids'] = $tenement_ids;
            unset($tenement_ids);
        }
        unset($post['plate_f'], $post['house_unit'], $post['house_floor']);

        $result = $this->user->post('/tenement/userlist',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }
        $lists = $result['content']['lists'];
        $out_reason_arr = array_column($lists,'out_reason_tag_id');
        $rescue_type_arr = array_column($lists,'rescue_type_tag_id');
        $pet_type_arr = array_column($lists,'pet_type_tag_id');
        $tenement_type_arr = array_column($lists,'tenement_type_tag_id');
        $license_tag_arr = array_column($lists,'license_tag_id');
        $customer_type_arr = array_column($lists,'customer_type_tag_id');
        $car_type__arr = array_column($lists,'car_type_tag_id');
        $sex_arr = array_column($lists,'sex');
        $pet_type_arr = implode(',',$pet_type_arr);
        $pet_type_arr = array_unique(explode(',',$pet_type_arr));
        $tags = array_filter(array_merge($out_reason_arr,$rescue_type_arr,$pet_type_arr,$tenement_type_arr,
            $car_type__arr,$sex_arr,$license_tag_arr,$customer_type_arr));
        $tag_res = $this->tag->post('/tag/lists',['tag_ids'=>implode(',',$tags)]);
        if($tag_res['code']!=0){
            rsp_die_json(10002,$tag_res['message']);
        }
        $tag_content = array_column($tag_res['content'],null,'tag_id');
        $project_arr = array_unique(array_filter(array_column($lists,'project_id')));
        $project_res = $this->pm->post('/project/lists',['project_ids'=>$project_arr]);
        if($project_res['code']!=0){
            rsp_die_json(10002,$project_res['message']);
        }
        if($project_res['code']==0 && empty($project_res['content'])){
            rsp_die_json(10002,"项目查询失败");
        }
        $creator_arr = array_filter(array_unique(array_column($lists,'creator')));
        $editor_arr = array_filter(array_unique(array_column($lists,'editor')));
        $liable_arr = array_filter(array_unique(array_column($lists,'liable_employee_id')));
        $creator_res = $this->user->post('/employee/lists',['employee_ids'=>array_merge($creator_arr,$editor_arr,$liable_arr)]);
        if($creator_res['code']!=0){
            rsp_die_json(10002,$creator_res['message']);
        }
        $creator_content =  array_column($creator_res['content'],null,'employee_id');
        $project_content = array_column($project_res['content'],null,'project_id');
        foreach($result['content']['lists'] as $key=>$value){
            $result['content']['lists'][$key]['out_reason_name'] = isset($tag_content[$value['out_reason_tag_id']])?$tag_content[$value['out_reason_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['rescue_type_name'] = isset($tag_content[$value['rescue_type_tag_id']])?$tag_content[$value['rescue_type_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['pet_type_name'] = isset($tag_content[$value['pet_type_tag_id']])?$tag_content[$value['pet_type_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['customer_type_name'] = isset($tag_content[$value['customer_type_tag_id']])?$tag_content[$value['customer_type_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['license_tag_name'] = isset($tag_content[$value['license_tag_id']])?$tag_content[$value['license_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['tenement_type_name'] =isset($tag_content[$value['tenement_type_tag_id']])?$tag_content[$value['tenement_type_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['project_name'] =isset($project_content[$value['project_id']])?$project_content[$value['project_id']]['project_name']:'';
            $result['content']['lists'][$key]['creator'] = isset($creator_content[$value['creator']])?$creator_content[$value['creator']]['full_name']:'';
            $result['content']['lists'][$key]['editor'] = isset($creator_content[$value['editor']])?$creator_content[$value['editor']]['full_name']:'';
            $result['content']['lists'][$key]['liable_employee_name'] = isset($creator_content[$value['liable_employee_id']])?$creator_content[$value['liable_employee_id']]['full_name']:'';
            $result['content']['lists'][$key]['liable_employee_mobile'] = isset($creator_content[$value['liable_employee_id']])?$creator_content[$value['liable_employee_id']]['mobile']:'';
            $result['content']['lists'][$key]['birth_day'] = isTrueKey($result['content']['lists'][$key], 'birth_day') ? date('Y-m-d', strtotime($result['content']['lists'][$key]['birth_day'])) : '';
            $result['content']['lists'][$key]['license_tag_id'] = $value['license_tag_id'] != 0 ?$value['license_tag_id']:'';
            $result['content']['lists'][$key]['tenement_type_tag_id'] = $value['tenement_type_tag_id'] != 0 ?$value['tenement_type_tag_id']:'';
            $result['content']['lists'][$key]['customer_type_tag_id'] = $value['customer_type_tag_id'] != 0 ?$value['customer_type_tag_id']:'';
            $result['content']['lists'][$key]['sex'] = $value['sex'] != 0 ?$value['sex']:'';
            $pet_str = [];
            if(!empty($value['pet_type_tag_id'])){
                $pet_tags = explode(',',$value['pet_type_tag_id']);
                foreach($pet_tags as $vl){
                    $pet_str[]= isset($tag_content[$vl])?$tag_content[$vl]['tag_name']:'';
                }
            }
            $result['content']['lists'][$key]['pet_str'] = implode(',',$pet_str);
        }
        rsp_success_json($result['content'],'查询成功');
    }
    
    private function getTenementIds($params){
        $query = [];
        $query['plate_f'] = $params['plate_f'] ?? null;
        $query = array_filter($query, function ($m) {
            return !is_null($m) && $m !== '';
        });
        if (empty($query)) {
            return [];
        }
        $query['page'] = 1;
        $query['pagesize'] = 100;
        $result = $this->user->post('/tenement/car/lists', $query);
        if (!isset($result['code']) || $result['code'] != 0) {
            rsp_die_json(10002, '查询失败 '.($result['message'] ?: ''));
        }
        $tenement_ids = array_unique(array_filter(array_column($result['content'], 'tenement_id')));
        return $tenement_ids ?: ['888888888888888888888888'];
    }

    /**
     * @param array $post
     * @throws Exception
     * 业主添加
     */
    public function TenementAdd($post=[])
    {
        if(empty($post['tenement_type_tag_id'])){
            rsp_die_json(10001, '住户类型不能为空');
        }
        if($post['tenement_type_tag_id']== '411'){
            $check_params_info = checkParams($post, ['real_name','mobile']);
        }else{
            $check_params_info = checkParams($post, ['real_name','mobile',]);
        }
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $tenement_res = $this->resource->post('/resource/id/generator',['type_name'=>'tenement']);
        if($tenement_res['code']!=0 || ($tenement_res['code']==0 && empty($tenement_res['content']))){
            rsp_die_json(10001, '资源id生成失败');
        }
        if(!empty($_SESSION['member_project_id'])){
            $post['project_id'] = $_SESSION['member_project_id'];
        }
        $post['tenement_id'] = $tenement_res['content'];
        $post['creator'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
        $post['editor'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';

        $tenement_id = $post['tenement_id'];
        if ( isTrueKey($post, 'face_resource_id') ) {
            $face_model = new \Face\FaceModel();
            $person_exists = $face_model->personExists(['project_id' => $post['project_id'], 'face_resource_id' => $post['face_resource_id']]);
            if ($person_exists) {
                rsp_die_json(10001,'人脸已存在');
            }
        }
        if (isTrueKey($post, 'house_list')) {
            $post['house_list'] = array_map(function ($m) {
                if (!isTrueKey($m, 'space_id')) {
                    rsp_die_json(10001, '请选择房屋');
                }
                $space = $this->pm->post('/space/show', ['space_id' => $m['space_id']]);
                $space = $space['content'] ?? [];
                if (!$space) {
                    rsp_die_json(10001, '房屋不存在');
                }
                if ($space['space_type'] !== 1394) {
                    rsp_die_json(10001, '请选择具体房屋');
                }
                $houses = $this->pm->post('/house/basic/lists', ['space_id' => $m['space_id']]);
                $houses = $houses['content'] ?? [];
                if (!$houses) {
                    rsp_die_json(10001, '房屋不存在');
                }
                $m['house_id'] = $houses[0]['house_id'];
                return $m;
            }, $post['house_list']);
        }
        $result = $this->user->post('/tenement/useradd', $post);
        if ($result['code'] != 0) {
            rsp_die_json(10002, $result['message']);
        }

        // 人脸
        if ( isTrueKey($post, 'face_resource_id') ) {
            $face_model->refreshFace([
                'tenement_id' => $tenement_id,
                'project_id' => $post['project_id'],
                'face_resource_id' => $post['face_resource_id'],
            ]);
            (new \Device\DeviceModel())->toggleTenementPrivileges($tenement_id);
        }

        rsp_success_json($result['content'],'添加成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 附加信息展示
     */
    public function Tenementextlist($post=[])
    {
        $result = $this->user->post('/tenement/extlist',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json(['car_list'=>[],'house_list'=>[],'label_list'=>[]],'查询成功');
        }
        if(!empty($result['content']['car_list'])){
            $car_type_arr = array_column($result['content']['car_list'],'car_type_tag_id');
            $tags = array_unique(array_filter($car_type_arr));
            $tag_res = $this->tag->post('/tag/lists',['tag_ids'=>implode(',',$tags)]);
            if($tag_res['code']!=0){
                rsp_die_json(10002,$tag_res['message']);
            }
            $tag_content = array_column($tag_res['content'],null,'tag_id');
            if(!empty($result['content']['car_list'])){
                foreach($result['content']['car_list'] as $key=>$value){
                    $result['content']['car_list'][$key]['car_type_tag_name'] = isset($tag_content[$value['car_type_tag_id']])?$tag_content[$value['car_type_tag_id']]['tag_name']:'';
                }
            }
        }
        if(!empty($result['content']['label_list'])){
            $tenement_tag_arr = array_column($result['content']['label_list'],'tenement_tag_id');
            $tags = array_unique(array_filter($tenement_tag_arr));
            $tag_res = $this->tag->post('/tag/lists',['tag_ids'=>implode(',',$tags)]);
            if($tag_res['code']!=0 ){
                rsp_die_json(10002,$tag_res['message']);
            }
            $tag_content = array_column($tag_res['content'],null,'tag_id');
            if(!empty($result['content']['label_list'])){
                foreach($result['content']['label_list'] as $key=>$value){
                    $result['content']['label_list'][$key]['tenement_tag_name'] = isset($tag_content[$value['tenement_tag_id']])?$tag_content[$value['tenement_tag_id']]['tag_name']:'';
                }
            }
        }
        if(!empty($result['content']['house_list'])){
            $house_content = array_unique(array_filter(array_column($result['content']['house_list'],'house_id')));
            if(!empty($house_content)){
                $house_arr =  $this->pm->post('/house/lists',['house_ids'=>$house_content]);
                $house_res = array_column($house_arr['content'],null,'house_id');

                // space
                $space_ids = array_unique(array_filter(array_column($house_res, 'space_id')));
                $space_branches = $this->pm->post('/space/batchBranch', ['space_ids' => $space_ids]);
                $space_branches = $space_branches['content'] ?? [];
            }else{
                $house_res = [];
                $space_branches = [];
            }

            $cell_content = array_unique(array_filter(array_column($result['content']['house_list'],'cell_id')));
            if(!empty($cell_content)){
                $cell_arr =  $this->pm->post('/house/cells/lists',['cell_ids'=>$cell_content]);
                $cell_res = array_column($cell_arr['content'],null,'cell_id');
            }else{
                $cell_res = [];
            }
            foreach ($result['content']['house_list'] as $key => $value) {
                $result['content']['house_list'][$key]['space_id'] = isset($house_res[$value['house_id']]) ? $house_res[$value['house_id']]['space_id'] : '';
                $branch_info = SpaceModel::parseBranch($space_branches[$result['content']['house_list'][$key]['space_id']] ?? []);
                $result['content']['house_list'][$key]['space_name_full'] = $branch_info['space_name_full'] ?? '';
                $result['content']['house_list'][$key]['cell_name'] = isset($cell_res[$value['cell_id']]) ? $cell_res[$value['cell_id']]['cell_name'] : '';
                $result['content']['house_list'][$key]['in_time'] = !empty($value['in_time']) ? date('Y-m-d H:i:s', $value['in_time']) : '';
                $result['content']['house_list'][$key]['out_time'] = !empty($value['out_time']) ? date('Y-m-d H:i:s', $value['out_time']) : '';
            }
        }
        rsp_success_json($result['content'],'查询成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 业主添加
     */
    public function TenementUpdate($post=[])
    {
        if(empty($post['tenement_type_tag_id'])){
            rsp_die_json(10001, '住户类型不能为空');
        }
        if($post['tenement_type_tag_id']== '411'){
            $check_params_info = checkParams($post, ['real_name','mobile']);
        }else{
            $check_params_info = checkParams($post, ['real_name','mobile']);
        }
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        if(!empty($_SESSION['member_project_id'])){
            $post['project_id'] = $_SESSION['member_project_id'];
        }
        $post['editor'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';

        // 人脸
        $tenement_id = $post['tenement_id'];
        if ( isTrueKey($post, 'face_resource_id') ) {
            (new \Face\FaceModel())->refreshFace([
                'tenement_id' => $tenement_id,
                'project_id' => $post['project_id'],
                'face_resource_id' => $post['face_resource_id'],
            ]);
        }
        if (isTrueKey($post, 'house_list')) {
            $post['house_list'] = array_map(function ($m) {
                if (!isTrueKey($m, 'space_id')) {
                    rsp_die_json(10001, '请选择房屋');
                }
                $space = $this->pm->post('/space/show', ['space_id' => $m['space_id']]);
                $space = $space['content'] ?? [];
                if (!$space) {
                    rsp_die_json(10001, '房屋不存在');
                }
                if ($space['space_type'] !== 1394) {
                    rsp_die_json(10001, '请选择具体房屋');
                }
                $houses = $this->pm->post('/house/basic/lists', ['space_id' => $m['space_id']]);
                $houses = $houses['content'] ?? [];
                if (!$houses) {
                    rsp_die_json(10001, '房屋不存在');
                }
                $m['house_id'] = $houses[0]['house_id'];
                return $m;
            }, $post['house_list']);
        }
        $result = $this->user->post('/tenement/userupdate', $post);
        if ($result['code'] != 0) {
            rsp_die_json(10002, $result['message']);
        }
        //住户身份审核通过后  之前以游客绑定的房产迁移过来
        if(isTrueKey($post,'tenement_check_status') && $post['tenement_check_status'] == 'Y'){
            $this->_client_house_move($post);
        }

        if ( isTrueKey($post, 'face_resource_id') ) {
            (new \Device\DeviceModel())->toggleTenementPrivileges($tenement_id);
        }

        //事件触发器推送
        $result = Comm_EventTrigger::push('wos_trail_user_update', ['tenement_id' => $tenement_id]);
        if (empty($result)) {
            info(__METHOD__, ['error' => '住户更新事件触发器推送失败', 'tenement_id' => $tenement_id]);
        }
        rsp_success_json($result['content'],'更新成功');
    }

    /**
     * @param array $params
     * @throws Exception
     * 游客房产数据迁移到住户
     */
    private function _client_house_move($params){
        $client_lists = $this->user->post('/client/lists',['user_id'=>$params['user_id'] ]);
        if($client_lists['code'] != 0 || !$client_lists['content']) return ;

        $client_ids = array_unique(array_column($client_lists['content'],'client_id'));
        $client_house_lists = $this->user->post('/clienthouse/lists',['client_ids'=>$client_ids]);
        if($client_house_lists['code'] != 0 || !$client_house_lists['content']) return ;

        foreach($client_house_lists['content'] as $v){
            //查询是否是该项目下的房产
            $pm_house_show = $this->pm->post('/house/show',[
                'project_id'=>$params['project_id'],
                'house_id'=>$v['house_id'],
            ]);
            if($pm_house_show['code'] != 0 || empty($pm_house_show['content']) ) continue;

            $house_show = $this->user->post('/house/lists',[
                'tenement_id'=>$params['tenement_id'],
                'house_id'=>$v['house_id'],
                'page'=>1,
                'pagesize'=>1
            ]);
            if($house_show['code'] != 0 ) continue;

            if(empty($house_show['content']) ){
                $house_add_params = [
                    'tenement_id'=>$params['tenement_id'],
                    'house_id'=>$v['house_id'],
                    'tenement_house_status'=>'N',
                    'tenement_identify_tag_id'=>'917',
                ];
                $result = $this->user->post('/house/add',$house_add_params);
                if($result['code'] != 0) continue;
            }
            $this->user->post('/clienthouse/del',['c_house_id'=>$v['c_house_id'],'house_id'=>$v['house_id'] ]);
        }
    }


    /**
     * @param array $post
     * @throws Exception
     * 根据用户id | 手机号码 查询所有房产信息
     */
    public function user_house_lists($post=[]){
        if(empty($post['mobile']) && empty($post['project_id'])){
            rsp_die_json(10001, '参数缺失');
        }
        $user_data = ['mobile'=>$post['mobile'],'tenement_check_status'=>'Y'];
        if(!empty($post['project_id'])){
            $user_data['project_id'] = $post['project_id'];
        }
        $result = $this->user->post('/tenement/lists',$user_data);
        log_message('----user_house_lists1----'.json_encode($result));
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }
        $tenement_ids = array_unique(array_column($result['content'],'tenement_id') );
        $data = $this->user->post('/house/lists',['tenement_ids'=>$tenement_ids,'tenement_house_status'=>'Y']);
        log_message('----user_house_lists2----'.json_encode($data));
        if($data['code'] != 0){
            rsp_die_json(10003,$data['message']);
        }
        if(empty($data['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }
        $house_ids = array_unique(array_column($data['content'], 'house_id'));
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
}

