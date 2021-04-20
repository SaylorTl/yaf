<?php

final class Usercenter extends Base
{

    //微信小程序 注册接口
    public function register($post=[]){
        $check_params_info = checkParams($post, ['client_id','mobile','access_token','code','source']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        if (empty($_SESSION['client_id']) || empty($_SESSION['third_party_app_id'])) {
            rsp_die_json(10001, '用户未登录');
        }
        $source = "smscode_".$post['source'];
        $key = $source.'_'.$post['mobile'];
        $code = Comm_Redis::getInstance()->get($key);
        if( isTrueKey($post,'code') == false || $post['code'] != $code ){
            rsp_die_json(10011,'短信验证码错误');
        }
        $client_res = $this->user->post('/client/show',['client_id'=>$_SESSION['client_id']]);
        if(0 == $client_res['code'] && !empty($client_res['content']['user_id'])){
            rsp_success_json([],'登录成功');
        }
        $userList = $this->user->post('/user/show',['mobile'=>$post['mobile']]);
        if($userList['code']==0 && !empty($userList['content'])){//用户已存在逻辑
            $user_id = $userList['content']['user_id'];
            $clientData = $this->user->post('/client/lists',['user_id'=>$user_id,'app_id'=>$_SESSION['third_party_app_id']]);
            if($clientData['code'] == 0 && !empty($clientData['content'])){
                rsp_die_json(10001,'该手机号已被注册');
            }
        }else{
            $result = $this->user->post('/user/add',['mobile'=>$post['mobile']]);
            if(0 != $result['code']){
                rsp_die_json(10001,'注册失败');
            }
            $user_id = $result['content'];
        }
        $rps = $this->user->post('/client/update',['client_id'=>$_SESSION['client_id'],'user_id'=>$user_id]);
        if(0 != $rps['code']){
            rsp_die_json(10001,'绑定失败');
        }
        $json = $this->auth2->post('/login/checkMobile',['access_token'=>$post['access_token'],'mobile'=>$post['mobile']]);
        if( (int)$json['code'] != 0 ){
            rsp_die_json(10001,$json['message']);
        }
        rsp_success_json($json['content'],'登录成功');
    }
    /**
     * @param array $post
     * 短信验证码
     */
    public function smsCaptcha($post=[]){
        if( isTrueKey($post,'mobile') == false || !isMobile($post['mobile']) ){
            rsp_die_json(10001,'手机号不能为空');
        }
        if(isTrueKey($post,'source','mobile') == false){
            rsp_die_json(10001,'参数缺失');
        }
        $redis = Comm_Redis::getInstance();
        $source = "smscode_".$post['source'];
        $key = $source.'_'.$post['mobile'];
        $waitkey = $source.'_'.$post['mobile'].'_expire';
        $isExpire = $redis->get($key);
        if( $isExpire ){
            rsp_die_json(10011,'发送频繁，请稍后重试');
        }
        $code = mt_rand(10000,99999);
        $redis->set($key,$code);
        $redis->expire($key,300);

        $redis->set($waitkey,1);
        $redis->expire($key,60);
        $result =  Comm_Sms::sendSms($post['mobile'],['code'=>$code,'minute'=>5]);
        if( $result['code'] != 0 ){
            rsp_die_json(10007,'发送失败');
        }
        rsp_success_json('','发送成功');
    }

    public function userDevice($post=[]){
        if( isTrueKey($post,'project_id') == false && isTrueKey($post['user_id'])== false ){
            rsp_die_json(10001,'参数错误');
        }
        $userDevices = $this->user->post('/userdevice/userMergeVisitor',['project_id'=>$post['project_id'],'user_id'=>$post['user_id']]);
        if(0 != $userDevices['code']){
            rsp_die_json(10001,'用户设备查询失败');
        }
        $user_device_ids = array_unique(array_column($userDevices['content'],'device_id'));
        log_message('----用户设备权限1------' .json_encode($userDevices,true));

        $project =  $this->pm->post('/project/lists', ['project_id' => $post['project_id']]);
        $project = ($project['code'] === 0 && $project['content']) ? $project['content'][0] : [];
        $project_device_arr = isset($project['project_device_templates'])?$project['project_device_templates']:[];

        $project_device_templates = array_column($project_device_arr, 'device_template_id');

        log_message('----用户设备权限2------' .json_encode($project_device_templates,true));
        $devices = [];
        $tenement_user = $this->user->post('/tenement/lists',['user_id'=>$post['user_id'],
            'project_id'=>$post['project_id']]);
        if($tenement_user['code']!=0){
            log_message('----用户设备权限3----'.json_encode($tenement_user));
            rsp_die_json(10003,'用户信息查询失败');
        }
        if(!empty($tenement_user['content'])){
            if (!empty($project_device_templates)) {
                $prject_device_ids = $this->pm->post('/device/v2/lists', ['project_id' => $post['project_id']]);
                $prject_device_ids = ($prject_device_ids['code'] === 0 && $prject_device_ids['content']) ? $prject_device_ids['content'] : [];
                $temp_device_ids = array_unique(array_column($prject_device_ids,'device_id'));
                $devices = $this->device->post('/device/ids', ['device_ids' => $temp_device_ids,
                    'device_template_ids' => $project_device_templates]);
                $devices = ($devices['code'] === 0 && $devices['content']) ? $devices['content'] : [];
                log_message('----用户设备权限4------' .json_encode($devices,true));
            }
            $project_device_ids = array_unique(array_filter(array_column($devices, 'device_id')));
            $device_ids = array_unique(array_merge($project_device_ids,$user_device_ids));
        }else{
            $device_ids = array_unique(array_merge($user_device_ids));
        }

//        $device_ids = array_unique($user_device_ids);
        log_message('----用户设备权限5------' .json_encode($device_ids,true));
        if(empty($device_ids)){
            rsp_success_json([],'查询成功');
        }
        $deivce_lists = $this->device->post('/device/lists', ['device_ids'=>$device_ids]);
        if(0 != $deivce_lists['code']){
            rsp_die_json(10001,'设备详情查询失败');
        }
        $device_arr = array_column($deivce_lists['content'],null,'device_id');
        $device_template_ids = array_column($deivce_lists['content'],'device_template_id');
        $device_template_arr = $this->device->post('/device/template/lists', ['device_template_ids'=>$device_template_ids]);
        if(0 != $device_template_arr['code']){
            rsp_die_json(10001,'设备模板详情查询失败');
        }

        $device_template_arr = array_column($device_template_arr['content'],null,'device_template_id');
        $deviceListsContent = $deivce_lists['content'];
        foreach($deviceListsContent as $key=>$value){
            if(!empty($device_arr[$value['device_id']])){
                $detail = json_decode($device_arr[$value['device_id']]['device_vendor_detail'], true);
                $device_mac = isset($detail['mac']) ? str_replace(':', '', strtoupper($detail['mac'])) : '';
                $deviceListsContent[$key]['abilities'] = array_column($device_arr[$value['device_id']]['device_ability_tag_ids'], 'device_ability_tag_id', null);
                $deviceListsContent[$key]['device_name'] =  $device_arr[$value['device_id']]['device_name'];
                $deviceListsContent[$key]['device_mac'] = $device_mac;
                $deviceListsContent[$key]['device_key'] = $detail['key'] ?? '';
                $deviceListsContent[$key]['vendor_id'] = $device_arr[$value['device_id']]['vendor_id'];
                $deviceListsContent[$key]['device_template_type_tag_id'] = $device_template_arr[$device_arr[$value['device_id']]['device_template_id']]['device_template_type_tag_id'];
                $deviceListsContent[$key]['vendor_id'] = $device_template_arr[$device_arr[$value['device_id']]['device_template_id']]['vendor_id'];
                $deviceListsContent[$key]['user_id'] =$post['user_id'];
                $deviceListsContent[$key]['project_id'] = $post['project_id'];
            }else{
                continue;
            }
        }
        log_message('----用户设备权限6------' .json_encode($deviceListsContent,true));
        rsp_success_json($deviceListsContent,'查询成功');
    }

}