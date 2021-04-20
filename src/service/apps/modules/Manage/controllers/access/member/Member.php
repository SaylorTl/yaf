<?php
final class Member extends Base
{
    /**
     * @param $post
     * 账号列表
     * 124305906859193139210016
     */
    public function memberList($post){
        unsetEmptyParams($post);
        $check_params_info = checkEmptyParams($post, ['page','pagesize']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $params = [ 'page'=>$post['page'],
            'pagesize'=>$post['pagesize'],];
        if(!empty($post['full_name'])){
            $params['full_name'] =$post['full_name'];
        }
        if(!empty($post['time_begin'])){
            $params['time_begin'] =$post['time_begin'];
        }
        if(!empty($post['time_end'])){
            $params['time_end'] =$post['time_end'];
        }
        if(!empty($post['status'])){
            $params['status'] =$post['status'];
        }
        $role_res = $this->user->post('/member/userlist',$params );
        if($role_res['code']==0){
            if(!empty($role_res['content'])){
                foreach($role_res['content']['lists'] as $key=>$value){
                    $role_res['content']['lists'][$key]['end_time'] = !empty($value['end_time'])?date('Y-m-d',$value['end_time']):'';
                    $role_res['content']['lists'][$key]['begin_time'] = !empty($value['end_time'])?date('Y-m-d',$value['begin_time']):'';
                }
            }
            rsp_success_json($role_res['content'],'账号查询成功');
        }
        rsp_die_json(10004, $role_res['message']);
    }

    /**
     * @param $post
     * 设置管理员
     */
    public function setMember($post){
        $check_params_info = checkEmptyParams($post, ['employee_id','password']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        if(isset($post['login_max_num'])){
            $post['login_max_num']>=1 or rsp_die_json(10004,'最大登陆数不能小于1');
        }
        $member_params = [
            'employee_id' => $post['employee_id'],
            'type' => $post['type'],
            'password' => isset($post['password']) ?  $post['password']: '',
            'login_max_num' => isset($post['login_max_num']) ? $post['login_max_num'] : 1,
            'end_time'=>isset($post['end_time']) ? $post['end_time'] : '',
            'begin_time'=>isset($post['begin_time']) ? $post['begin_time'] : '',
            'status'=>isset($post['status']) ? $post['status'] : 'Y',
        ];
        $member_res = $this->user->post('/member/add',$member_params );
        if($member_res['code']!=0){
            rsp_die_json(10009, $member_res['message']);
        }
        if(!empty($post['user_name'])){
            $employeeParams = [ 'employee_id' => $post['employee_id'],
                'user_name'=>$post['user_name']];
            $employee_res =  $this->user->post('/employee/update',$employeeParams);
            if($employee_res['code']!=0){
                rsp_die_json(10005, $employee_res['message']);
            }
        }
        $user_params = [
            'employee_id' => $post['employee_id'],
        ];
        $user_res = $this->access->post('/user/add',$user_params );
        if($user_res['code']!=0){
            rsp_die_json(10008, $user_res['message']);
        }
        AuthEvents::updateMemberAccess($post['employee_id']);
        rsp_success_json($user_res['content'],'管理员添加成功');
    }

    /**
     * @param $post
     * 设置账号权限
     */
    public function setMemberPrivleges($post){
        $user_params = ['employee_id'=>$post['employee_id']];
        $user_res = $this->access->post('/user/show',$user_params );
        if(empty($user_res['content'])){
            $user_add_res = $this->access->post('/user/add',$user_params );
            if(empty($user_add_res['content'])){
                rsp_die_json(10004, $user_add_res['message']);
            }
            $ac_user_id = $user_add_res['content'];
        }else{
            $ac_user_id = $user_res['content']['ac_user_id'];
        }
        $params = ['role_id'=>$post['role_id'],
            'ac_user_id'=>$ac_user_id];
        $roleresource_res = $this->access->post('/userrole/add',$params );
        if($roleresource_res['code']==0){
            rsp_success_json('','管理员权限添加成功');
        }
        rsp_die_json(10004, $roleresource_res['message']);
    }

    /**
     * @param $post
     * 删除账号
     */
    public function delMember($post){
        $check_params_info = checkEmptyParams($post, ['employee_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $post['status'] = 'N';
        $member_res = $this->access->post('/employee/update',$post );
        if($member_res['code']!=0){
            rsp_die_json(10004, $member_res['message']);
        }
        AuthEvents::updateMemberAccess($post['employee_id']);
        rsp_success_json('','管理员删除成功');
    }

    /**
     * @param $post
     * 编辑账号
     */
    public function editMember($post){
        $check_params_info = checkEmptyParams($post, ['employee_id','type']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $member_params = [
            'employee_id' => $post['employee_id'],
            'type' => $post['type'],
            'login_max_num' => isset($post['login_max_num']) ? $post['login_max_num'] : 1,
        ];
        if(isset($post['old_password'])&&isset($post['user_name'])){
            $member_password_res = $this->user->post('/member/checkpassowrd',['user_name'=>$post['user_name'],
                'password'=>$post['old_password']] );
            if($member_password_res['code']!=0){
                rsp_die_json(10004, '旧密码错误');
            }
        }
        if(isset($post['password'])){
            $member_params['password'] = $post['password'];
        }
        if(isset($post['login_max_num'])){
            $post['login_max_num']>=1 or rsp_die_json(10004,'最大登陆数不能小于1');
            $member_params['login_max_num'] = $post['login_max_num'];
        }
        if(isset($post['begin_time'])){
            $member_params['begin_time'] = $post['begin_time'];
        }
        if(isset($post['end_time'])){
            $member_params['end_time'] = $post['end_time'];
        }
        $member_res = $this->user->post('/member/update',$member_params );
        if($member_res['code']!=0){
            rsp_die_json(10004, $member_res['message']);
        }
        $user_params = [
            'employee_id' => $post['employee_id'],
        ];
        if(isset($post['status'])){
            $user_params['status'] = $post['status'];
        }
        if(isset($post['user_name'])){
            $user_params['user_name'] = $post['user_name'];
        }
        $member_res = $this->user->post('/employee/update',$user_params );
        if($member_res['code']!=0){
            rsp_die_json(10004, $member_res['message']);
        }
        AuthEvents::updateMemberAccess($post['employee_id']);
        $cfg = getConfig('ms.ini');
        $oauthUrl = $cfg->auth2->url ?? '';
        curl_json("post",$oauthUrl."/redis/delete/authUser",['access_token'=>session_id(),'employee_id'=>$post['employee_id']]);
        rsp_success_json('','管理员编辑成功');
    }

    /**
     * @param $post
     * 获取账号权限
     */
    public function getAccessList($post){
        unsetEmptyParams($post);
        $check_params_info = checkEmptyParams($post, ['employee_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $user_params = [
            'employee_id' => $post['employee_id'],
        ];
        $user_res = $this->access->post('/user/lists',$user_params );
        if($user_res['code']!=0){
            rsp_die_json(10004, $user_res['message']);
        }
        rsp_success_json($user_res['content'],'查询成功');
    }

    /**
     * @param $post
     * 设置账号权限
     */
    public function setAccess($post){
        $check_params_info = checkEmptyParams($post, ['employee_id','source_arr']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $user_params = [
            'employee_id' => $post['employee_id'],
            'source_arr' => $post['source_arr'],
        ];
        $user_res = $this->access->post('/user/batchAdd',$user_params );
        if($user_res['code']!=0){
            rsp_die_json(10004, $user_res['message']);
        }
        AuthEvents::updateSourceAccess($post['employee_id']);
        rsp_success_json($user_res['content'],'授权成功');
    }
}