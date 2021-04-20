<?php

use Project\SpaceModel;

final class Visitorapply extends Base
{

    /**
     * @param array $post
     * @throws Exception
     * 访客设备添加
     */
    public function visitorApplyAdd($post=[])
    {
        if(empty($_SESSION['member_project_id'])){
            rsp_die_json(10002,'项目id不存在');
        }
        $apply_user = $this->user->post('/user/show',['mobile'=>$post['apply_mobile']]);
        if($apply_user['code']!=0){
            log_message('----userShow----'.json_encode($apply_user));
            rsp_die_json(10002,'用户信息查询失败');
        }
        $post['apply_user_id'] = $apply_user['content']['user_id']??'';
        $tenement_user = $this->user->post('/tenement/lists',['mobile'=>$post['tenement_mobile']]);
        if($tenement_user['code']!=0){
            log_message('----userShow----'.json_encode($tenement_user));
            rsp_die_json(10002,'用户信息查询失败');
        }
        $post['tenement_user_id'] = $tenement_user['content'][0]['user_id'] ??'';
        $post['tenement_name'] = $post['tenement_name']??$tenement_user['content'][0]['real_name'];
        if(!empty($post['plate'])){
            if(!isPlate($post['plate'])){
                rsp_die_json(10002,'车牌格式错误');
            }
        }
        $post['apply_source_tag_id'] = $post['apply_source_tag_id']??'1181';
        $post['apply_status_tag_id'] = $post['apply_status_tag_id']??'1168';
        $post['check_status_tag_id'] = $post['check_status_tag_id']??'1172';
        $post['create_employee_id'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
        if(!empty($post['plate'])){
            if(!isPlate($post['plate'])){
                rsp_die_json(10004,'车牌格式错误');
            }
            $visitor_car_res = $this->user->post('/visitorapply/lists',['plate'=>$post['plate'],'project_id'=>$post['project_id'],'apply_status_tag_id'=>'1166','check_status_tag_id'=>'1170']);
            if($visitor_car_res['code']!=0){
                log_message('----visitorApplyAdd----'.json_encode($visitor_car_res));
                rsp_die_json(10003,'车辆信息查询失败');
            }
            if($visitor_car_res['code']==0 && !empty($visitor_car_res['content'])){
                log_message('----visitorApplyAdd----'.json_encode($visitor_car_res));
                rsp_die_json(10003,'车辆已被授权，无需重复申请，可以直接使用该车辆！');
            }
        }
        if(!empty($post['apply_user_id'])  && !empty($post['tenement_user_id']) ){
            $visitor_check_res = $this->user->post('/visitorapply/lists',['apply_user_id'=>$post['apply_user_id'],'project_id'=>$post['project_id'],
                'apply_status_tag_id'=>'1166','check_status_tag_id'=>'1170','tenement_user_id'=>$post['tenement_user_id']]);
            if($visitor_check_res['code']==0 && !empty($visitor_check_res['content'])){
                rsp_die_json(10003,'该用户已拥有可使用的有效权限，无需重复授权！');
            }
            $visitor_apply_res = $this->user->post('/visitorapply/lists',['apply_user_id'=>$post['apply_user_id'],'project_id'=>$post['project_id'],
                'apply_status_tag_id'=>'1168','check_status_tag_id'=>'1172','tenement_user_id'=>$post['tenement_user_id']]);
            if($visitor_apply_res['code']==0 && !empty($visitor_apply_res['content'])){
                rsp_die_json(10003,'该用户已发起申请，请您审核！');
            }
        }

        if (!isTrueKey($post, 'space_id')) {
            rsp_die_json(10001, '请选择房屋');
        }
        $space = $this->pm->post('/space/show', ['space_id' => $post['space_id']]);
        $space = $space['content'] ?? [];
        if (!$space) {
            rsp_die_json(10001, '房屋不存在');
        }
        if ($space['space_type'] !== 1394) {
            rsp_die_json(10001, '请选择具体房屋');
        }
        $houses = $this->pm->post('/house/basic/lists', ['space_id' => $post['space_id']]);
        $houses = $houses['content'] ?? [];
        if (!$houses) {
            rsp_die_json(10001, '房屋不存在');
        }
        $post['house_id'] = $houses[0]['house_id'];

        $result = $this->user->post('/visitorapply/generate',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        log_message('----visitorApplyAdd----'.json_encode($post));
        $project_res = $this->pm->post('/project/show',['project_id'=>$_SESSION['member_project_id']]);
        if ($project_res['code'] !== 0){
            rsp_die_json(10002,$project_res['message']);
        }
        if ($project_res['code'] == 0 &&empty($project_res['content'])){
            log_message('----visitorApplyAdd----该项目未配置微信appid'.json_encode($project_res));
            rsp_die_json(10002,'该项目未配置微信appid');
        }
        $oauth_third_app_res  = (new Comm_Gateway())->gateway(['jsfrom_source_id'=>$project_res['content']['client_app_id'],'third_type'=>'wechat'],
            'admin.appbinding.redisShow',['service'=>'auth2']);
        if ($oauth_third_app_res['code'] !== 0|| ($oauth_third_app_res['code'] == 0 &&empty($oauth_third_app_res['content']))){
            log_message('----visitorApplyAdd----该项目未配置微信appid'.json_encode($oauth_third_app_res));
            rsp_die_json(10002,"项目微信appid查询失败");
        }
        $post['third_party_app_id'] = $oauth_third_app_res['content']['third_app_id'];
        $title = '您好，您已被授权以下权限';
        $this->visitorAuth($post,$title);
        $this->tenementAuth($post);
        rsp_success_json($result['content'],'添加成功');
    }

    /**
     * @param array $post
     * 访客申请列表
     */
    public function visitorApplyLists($post=[])
    {
        unsetEmptyParams($post);
        $result = $this->user->post('/visitorapply/lists',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        unset($post['page']);
        unset($post['pagesize']);
        $count = $this->user->post('/visitorapply/count',$post);
        if($count['code']!=0 ){
            rsp_die_json(10002,$count['message']);
        }
        $project_arr = array_unique(array_filter(array_column($result['content'], 'project_id')));
        $project_res = $this->pm->post('/project/lists', ['project_ids' => $project_arr]);
        if ($project_res['code'] != 0) {
            rsp_die_json(10002, $project_res['message']);
        }
        $apply_source_arr = array_column($result['content'], 'apply_source_tag_id');
        $apply_status_arr = array_column($result['content'], 'apply_status_tag_id');
        $apply_identify_arr = array_column($result['content'], 'apply_identify_tag_id');
        $check_status_arr = array_column($result['content'], 'check_status_tag_id');
        $tags = array_unique(array_filter(array_merge($apply_source_arr, $apply_status_arr, $apply_identify_arr, $check_status_arr)));
        $tag_res = $this->tag->post('/tag/lists', ['tag_ids' => implode(',', $tags)]);
        if ($tag_res['code'] != 0) {
            rsp_die_json(10002, $tag_res['message']);
        }
        $tag_content = array_column($tag_res['content'], null, 'tag_id');
        $project_content = array_column($project_res['content'], null, 'project_id');

        $creator_arr = array_filter(array_unique(array_column($result['content'],'create_employee_id')));
        $update_arr = array_filter(array_unique(array_column($result['content'],'update_employee_id')));
        $creator_res = $this->user->post('/employee/lists',['employee_ids'=>array_merge($creator_arr,$update_arr)]);
        if($creator_res['code'] != 0){
            rsp_die_json(10004,$creator_res['message']);
        }
        $creator_content =  array_column($creator_res['content'],null,'employee_id');

        $create_user_arr = array_filter(array_unique(array_column($result['content'],'create_user_id')));
        $create_user_res = $this->user->post('/tenement/userlist',['user_ids'=>$create_user_arr]);
        if($create_user_res['code']!=0){
            rsp_die_json(10002,$create_user_res['message']);
        }
        $tenement_content = [];
        if(!empty($create_user_res['content']['lists'])){
            $tenement_content =  array_column($create_user_res['content']['lists'],null,'user_id');
        }

        // houses
        $houses = $this->pm->post('/house/basic/lists', ['house_ids' => array_unique(array_filter(array_column($result['content'],'house_id')))]);
        $houses = ($houses['code'] === 0 && $houses['content']) ? many_array_column($houses['content'], 'house_id') : [];

        // space
        $space_ids = array_unique(array_filter(array_column($houses, 'space_id')));
        $space_branches = $this->pm->post('/space/batchBranch', ['space_ids' => $space_ids]);
        $space_branches = $space_branches['content'] ?? [];

        foreach($result['content'] as $key=>$value){
            $result['content'][$key]['project_name'] = isset($project_content[$value['project_id']]) ? $project_content[$value['project_id']]['project_name'] : '';
            $result['content'][$key]['valid_date'] = !empty($value['expire_time'])?date("Y-m-d",strtotime($value['create_at']))." - ".date("Y-m-d",$value['expire_time']):"";
            $result['content'][$key]['apply_source_tag_name'] = isset($tag_content[$value['apply_source_tag_id']]) ? $tag_content[$value['apply_source_tag_id']]['tag_name'] : '';
            $result['content'][$key]['apply_status_tag_name'] = isset($tag_content[$value['apply_status_tag_id']]) ? $tag_content[$value['apply_status_tag_id']]['tag_name'] : '';
            $result['content'][$key]['apply_identify_tag_name'] = isset($tag_content[$value['apply_identify_tag_id']]) ? $tag_content[$value['apply_identify_tag_id']]['tag_name'] : '';
            $result['content'][$key]['check_status_tag_name'] = isset($tag_content[$value['check_status_tag_id']]) ? $tag_content[$value['check_status_tag_id']]['tag_name'] : '';
            $result['content'][$key]['expire_time'] = !empty($value['expire_time']) ? date('Y-m-d H:i:s', $value['expire_time']) : '';
            $result['content'][$key]['check_time'] = !empty($value['check_time'])?date('Y-m-d H:i:s',$value['check_time']):'';
            if(!empty($value['create_employee_id'])){
                $result['content'][$key]['creator'] = isset($creator_content[$value['create_employee_id']])?$creator_content[$value['create_employee_id']]['full_name']:'';
            }else{
                $result['content'][$key]['creator'] = !empty($tenement_content[$value['create_user_id']])?$tenement_content[$value['create_user_id']]['real_name']:"";
            }
            $result['content'][$key]['editor'] = isset($creator_content[$value['update_employee_id']])?$creator_content[$value['update_employee_id']]['full_name']:'';
            $result['content'][$key]['space_id'] = getArraysOfvalue($houses, $result['content'][$key]['house_id'], 'space_id');
            $branch_info = SpaceModel::parseBranch($space_branches[$result['content'][$key]['space_id']] ?? []);
            $result['content'][$key]['space_name_full'] = $branch_info['space_name_full'] ?? '';
        }
        rsp_success_json(['lists' => $result['content'], 'count' =>$count['content']??0],'查询成功');
    }

    /**
     * @param array $post
     * 访客设备列表
     */
    public function visitorDeviceLists($post=[])
    {
        $check_params_info = checkEmptyParams($post, ['visitor_apply_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $result = $this->user->post('/visitordevice/lists',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        $device_ids = array_column($result['content'],'device_id');
        $device_names='';
        if(!empty($device_ids)){
            $device_result = $this->device->post('/device/lists',["device_ids"=>$device_ids]);
            if($device_result['code']!=0 ){
                rsp_die_json(10002,$device_result['message']);
            }
            $device_name_arr = array_column($device_result['content'],'device_name');
            $device_names = implode(",",$device_name_arr);
        }
        rsp_success_json($device_names,'查询成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 访客审核
     */
    public function visitorApplyCheck($post=[])
    {
        if(empty($_SESSION['member_project_id'])){
            rsp_die_json(10002,'项目id不存在');
        }
        $visitor_apply_show = $this->user->post('/visitorapply/show',['visitor_apply_id'=>$post['visitor_apply_id']]);
        if($visitor_apply_show['code']!=0 ){
            log_message('----userShow----'.json_encode($visitor_apply_show));
            rsp_die_json(10002,'访客申请信息查询失败');
        }
        if (!isTrueKey($post, 'space_id')) {
            rsp_error_tips(10001, 'space_id');
        }
        $houses = $this->pm->post('/house/basic/lists', ['space_id' => $post['space_id']]);
        $houses = $houses['content'] ?? [];
        if (!$houses) {
            rsp_die_json(10001, '房屋不存在');
        }
        $post['house_id'] = $houses[0]['house_id'] ?? '';

        if(!empty($post['apply_mobile'])){
            $apply_user = $this->user->post('/user/show',['mobile'=>$post['apply_mobile']]);
            if($apply_user['code']!=0 ){
                log_message('----userShow----'.json_encode($apply_user));
                rsp_die_json(10002,'用户信息查询失败');
            }
            $post['apply_user_id'] = $apply_user['content']['user_id']??'';
        }
        if(!empty($post['tenement_mobile'])){
            $tenement_user = $this->user->post('/user/show',['mobile'=>$post['tenement_mobile']]);
            if($tenement_user['code']!=0 ){
                log_message('----userShow----'.json_encode($tenement_user));
                rsp_die_json(10002,'用户信息查询失败');
            }
            $post['tenement_user_id'] = $tenement_user['content']['user_id']??'';
        }
        if(!empty($post['apply_status_tag_id'])){
            $post['check_name'] = $_SESSION['member_full_name'];
            $post['check_employee_id'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
            $post['check_time'] = time();
        }
        $post['update_employee_id'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
        if(!empty($post['plate'])){
            if(!isPlate($post['plate'])){
                rsp_die_json(10002,'车牌格式错误');
            }
        }
        $post['face_resource_id'] = $visitor_apply_show['content']['face_resource_id'];
        $result = $this->user->post('/visitorapply/check',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        if(!empty($post['apply_status_tag_id'])){
            if(1166 == $post['apply_status_tag_id'] || 1167 == $post['apply_status_tag_id']){
                $project_res = $this->pm->post('/project/show',['project_id'=>$_SESSION['member_project_id']]);
                if ($project_res['code'] !== 0){
                    rsp_die_json(10002,$project_res['message']);
                }
                if ($project_res['code'] == 0 && empty($project_res['content'])){
                    log_message('----visitorApplyAdd----该项目未配置微信appid'.json_encode($project_res));
                    rsp_die_json(10002,'该项目未配置微信appid');
                }
                $oauth_third_app_res  = (new Comm_Gateway())->gateway(['jsfrom_source_id'=>$project_res['content']['client_app_id'],'third_type'=>'wechat'],'admin.appbinding.redisShow',['service'=>'auth2']);
                if ($oauth_third_app_res['code'] !== 0|| ($oauth_third_app_res['code'] == 0 &&empty($oauth_third_app_res['content']))){
                    log_message('----visitorApplyAdd----该项目未配置微信appid'.json_encode($oauth_third_app_res));
                    rsp_die_json(10002,"项目微信appid查询失败");
                }
                $post['third_party_app_id'] = $oauth_third_app_res['content']['third_app_id'];
                if(1166 == $post['apply_status_tag_id']){
                    $title = "您好，您的授权申请已通过";
                    $this->visitorAuth($post,$title);
                    $this->tenementAuth($post);
                }
                if(1167 == $post['apply_status_tag_id']){
                    $title = "您好，您的授权申请已拒绝";
                    $this->visitorAuth($post,$title);
                }
            }
        }
        rsp_success_json($result['content'],'审核成功');
    }

    //预约审核通知
    public function tenementCheck($data)
    {
        log_message('----门禁申请审核消息推送1-tenementCheck-----' .json_encode($data,true));
        if (empty($data['tenement_user_id'])){
            return false;
        }
        $config = getConfig('ms.ini');
        $tenement_check_short_tnum =  $config->get('tenement_check.short_tnum');
        $third_app_res  = (new Comm_Gateway())->gateway([
            'third_app_id'=>$data['third_party_app_id'],'app_type'=>'client',
            'third_type'=>'wechat','oauth_app_id'=>$_SESSION['oauth_app_id']],
            'admin.appbinding.show',['service'=>'auth2']);
        log_message('----门禁申请审核消息推送2-tenementCheck-----' .json_encode($third_app_res,true));
        if ($third_app_res['code'] !== 0){
            rsp_die_json(10002,$third_app_res['message']);
        }
        if ($third_app_res['code'] == 0 && empty($third_app_res['content'])){
            rsp_die_json(10002,'租户信息查询失败');
        }
        $tenement_check_url = $config->get('tenement_check.url');
        $tenement_check_url = $tenement_check_url."/".$third_app_res['content']['name_en']."/applyAudit";
        $wx_params = [
            'first' => ['value' => '您好！您有一条待审核的访问申请，请及时查阅。', 'color' => "#173177"],
            'keyword1' => ['value' => "您好！您有一条待审核的访问申请，请及时查阅。", 'color' => "#173177"],
            'keyword2' => ['value' =>  '无', 'color' => "#173177"],
            'keyword3' => ['value' =>  $data['apply_name']??'无', 'color' => "#173177"],
            'keyword4' => ['value' => date("Y-m-d H:i:s",time()), 'color' => "#173177"],
            'remark' => ['value' => '', 'color' => "#173177"],
        ];
        $send_params = [ 'title' => '预约审核通知','channel' => ['wechat'],
            'content' => '','wx_url' => $tenement_check_url."?id=".$data['visitor_apply_id'],
            'third_app_id' => $data['third_party_app_id'],
            'short_tnum'=>$tenement_check_short_tnum,
            'wx_params' => json_encode($wx_params, JSON_UNESCAPED_UNICODE),
            'source' => 'tenement_check_msg','mobile' => '','service_client_id' => 0,
        ];
        $apply_user = $this->user->post('/client/show',['user_id'=>$data['tenement_user_id'],'app_id'=>$data['third_party_app_id']]);
        if ($apply_user['code'] !== 0 || ($apply_user['code'] ==0 && empty($apply_user['content']))){
            log_message('----门禁申请审核消息推送2-tenementCheck-----' .json_encode($data,true));
            return false;
        }
        $send_params['open_id'] = $apply_user['content']['openid'];
        $result = $this->msg->post('/pushmsg/singleUser', $send_params);
        log_message('--门禁申请审核消息推送3-tenementCheck- '.json_encode($result,true).json_encode($send_params,true));
        return $result;
    }

    //审核结果通知消息
    public function tenementAuth($data)
    {
        log_message('----访客邀约授权通知消息推送1-tenementAuth-----' .json_encode($data,true));
        if (empty($data['tenement_user_id'])){
            return false;
        }
        $config = getConfig('ms.ini');
        $tenement_auth_short_tnum =  $config->get('tenement_auth.short_tnum');
        $wx_params = [
            'first' => ['value' => '授权访问成功。', 'color' => "#173177"],
            'keyword1' => ['value' => $data['apply_name']??'无', 'color' => "#173177"],
            'keyword2' => ['value' => date("Y-m-d H:i:s",time()), 'color' => "#173177"],
            'remark' => ['value' => '', 'color' => "#173177"]];
        $send_params = [
            'title' => '授权成功通知','channel' => ['wechat'],'content' => '',
            'wx_url' => '','third_app_id' => $data['third_party_app_id'],
            'short_tnum'=>$tenement_auth_short_tnum,
            'wx_params' => json_encode($wx_params, JSON_UNESCAPED_UNICODE),
            'source' => 'tenement_auth_msg','mobile' => '','service_client_id' => 0];
        log_message('----访客邀约授权通知推送2-tenementAuth-----' .json_encode($send_params,true));
        $apply_user = $this->user->post('/client/show',['user_id'=>$data['tenement_user_id'],'app_id'=>$data['third_party_app_id']]);
        if ($apply_user['code'] !== 0 || ($apply_user['code'] ==0 && empty($apply_user['content']))){
            log_message('----访客邀约授权通知推送4-tenementAuth-----' .json_encode($data,true));
            return false;
        }
        $send_params['open_id'] = $apply_user['content']['openid'];
        log_message('----访客邀约授权通知消息推送3-tenementAuth-----' .json_encode($send_params,true));
        $result = $this->msg->post('/pushmsg/singleUser', $send_params);
        log_message('--访客邀约授权通知通知推送5-tenementAuth- '.json_encode($result,true).json_encode($send_params,true));
        return $result;
    }

    //申请提交结果通知
    public function visitorSubmit($data)
    {
        log_message('----申请提交结果通知消息推送1-visitorSubmit-----' .json_encode($data,true));
        if (empty($data['apply_user_id'])){
            return false;
        }
        $wx_params = [
            'first' => ['value' => "尊敬的".$data['apply_name']."先生/小姐，您正在发起门禁通行申请", 'color' => "#173177"],
            'keyword1' => ['value' => "申请通行权限", 'color' => "#173177"],
            'keyword2' => ['value' => "无", 'color' => "#173177"],
            'keyword3' => ['value' => "提交成功", 'color' => "#173177"],
            'keyword4' => ['value' => "审核中", 'color' => "#173177"],
            'keyword5' => ['value' => date("Y-m-d H:i:s",time()), 'color' => "#173177"],
            'remark' => ['value' => "", 'color' => "#173177"],
        ];
        $config = getConfig('ms.ini');
        $visitor_apply_short_tnum =  $config->get('visitor_apply_submit.short_tnum');
        $send_params = ['title' => '申请提交结果通知','channel' => ['wechat'], 'content' => '',
            'wx_url' => '',
            'third_app_id' => $data['third_party_app_id'],
            'short_tnum'=>$visitor_apply_short_tnum,
            'wx_params' => json_encode($wx_params, JSON_UNESCAPED_UNICODE),
            'source' => 'visitor_apply_submit_msg','mobile' => '','service_client_id' => 0,
        ];
        $apply_user = $this->user->post('/client/show',['user_id'=>$data['apply_user_id'],'app_id'=>$data['third_party_app_id']]);
        if ($apply_user['code'] !== 0 || ($apply_user['code'] ==0 && empty($apply_user['content']))){
            log_message('----申请提交结果通知消息推送2-visitorSubmit-----' .json_encode($data,true));
            return false;
        }
        $send_params['open_id'] = $apply_user['content']['openid'];
        $result = $this->msg->post('/pushmsg/singleUser', $send_params);
        log_message('--申请提交结果通知推送3-visitorSubmit- '.json_encode($result,true).json_encode($send_params,true));
        return $result;
    }

    //访客授权通知
    public function visitorAuth($data,$title)
    {
        log_message('----申请授权审核结果通知消息推送1-visitorAuth-----' .json_encode($data,true));
        if (empty($data['apply_user_id'])){
            return false;
        }
        $project_res = $this->pm->post('/project/lists',['project_ids'=>[$data['project_id']]]);
        if ($project_res['code'] !== 0){
            rsp_die_json(10002,$project_res['message']);
        }
        if (empty($project_res['content'])){
            $project_name = '';
        }else{
            $project_name = $project_res['content'][0]['project_name'];
        }
        $wx_params = [
            'first' => ['value' => $title, 'color' => "#173177"],
            'keyword1' => ['value' => $project_name, 'color' => "#173177"],
            'keyword2' => ['value' => "无", 'color' => "#173177"],
            'keyword3' => ['value' => "无", 'color' => "#173177"],
            'keyword4' => ['value' => !empty($data['apply_count'])?$data['apply_count'].'次':'无限制', 'color' => "#173177"],
            'keyword5' => ['value' => !empty($data['apply_days'])?date("Y-m-d H:i",time())."至".date("Y-m-d H:i",strtotime("+{$data['apply_days']} day")):'无限制',
                'color' => "#173177"], //缴费时间
            'remark' => ['value' => '', 'color' => "#173177"]];
        $config = getConfig('ms.ini');
        $visitor_auth_short_tnum =  $config->get('visitor_auth.short_tnum');
        $send_params = [
            'title' => '访客授权通知','channel' => ['wechat'],'content' => '',
            'wx_url' => '','third_app_id' => $data['third_party_app_id'],
            'short_tnum'=>$visitor_auth_short_tnum,
            'wx_params' => json_encode($wx_params, JSON_UNESCAPED_UNICODE),
            'source' => 'visiroe_auth_msg','mobile' => '','service_client_id' => 0];
        $apply_user = $this->user->post('/client/show',['user_id'=>$data['apply_user_id'],'app_id'=>$data['third_party_app_id']]);
        if ($apply_user['code'] !== 0 || ($apply_user['code'] ==0 && empty($apply_user['content']))){
            log_message('----申请授权审核申请授权审核结果通知消息推送2-visitorAuth-----' .json_encode($data,true));
            return false;
        }
        $send_params['open_id'] = $apply_user['content']['openid'];
        $result = $this->msg->post('/pushmsg/singleUser', $send_params);
        log_message('--申请授权审核申请授权审核结果通知推送3-visitorAuth- '.json_encode($result,true).json_encode($send_params,true));
        return $result;
    }
}


