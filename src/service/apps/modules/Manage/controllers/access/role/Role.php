<?php
final class Role extends Base
{
    public function addRole($post){
        $params = [ 'p_role_id'=>empty($post['p_role_id'])?'1':$post['p_role_id'],
            'role_name'=>isset($post['role_name'])?$post['role_name']:'',
            'remark'=>isset($post['remark'])?$post['remark']:'',
            'is_disable'=>empty($post['is_disable'])?'N':$post['is_disable'],
            'role_status'=>empty($post['role_status'])?'Y':$post['role_status']];
        $role_res = $this->access->post('/role/add',$params );
        if($role_res['code']==0){
            rsp_success_json('','角色添加成功');
        }
        rsp_die_json(10004, $role_res['message']);
    }

    public function editRole($post){
        if(empty($post['role_id'])){
            rsp_die_json(10002,'role_id不能为空');
        }
        $params = ['p_role_id'=>empty($post['p_role_id'])?'':$post['p_role_id'],
            'role_id'=>empty($post['role_id'])?'':$post['role_id'],
            'role_name'=>isset($post['role_name'])?$post['role_name']:'',
            'remark'=>isset($post['remark'])?$post['remark']:'',
        ];
        if(!empty($post['is_disable'])){
            $params['is_disable'] = $post['is_disable'];
        }
        if(!empty($post['role_status'])){
            $params['role_status'] = $post['role_status'];
        }
        $role_res = $this->access->post('/role/update',$params );
        if($role_res['code']==0){
            //角色事件监听
            AuthEvents::updateRole($post['role_id']);
            rsp_success_json('','角色编辑成功');
        }
        rsp_die_json(10004, $role_res['message']);
    }

    public function roleList($post){
        unsetEmptyParams($post);
        $check_params_info = checkEmptyParams($post, ['page','pagesize']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $params = [ 'page'=>$post['page'],'pagesize'=>$post['pagesize']];
        if(!empty($post['role_status'])){
            $params['role_status'] =$post['role_status'];
        }
        if(!empty($post['time_begin'])){
            $params['time_begin'] =$post['time_begin'];
        }
        if(!empty($post['time_end'])){
            $params['time_end'] =$post['time_end'];
        }
        if(isset($post['role_name'])){
            $params['role_name'] =$post['role_name'];
        }
        if(!empty($post['is_disable'])){
            $params['is_disable'] =$post['is_disable'];
        }
        $role_res = $this->access->post('/role/lists',$params );
        if($role_res['code']==0){
            rsp_success_json($role_res['content'],'角色查询成功');
        }
        rsp_die_json(10004, $role_res['message']);
    }

}