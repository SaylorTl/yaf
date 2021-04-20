<?php
class Source extends Base
{

    public function getSourceList($post){
        unsetEmptyParams($post);
        $source = $this->access->post('/source/lists', ['page' => $post['page'],
            'pagesize'=>$post['pagesize'],'is_delete'=>'N']);
        if($source['code']==0){
            rsp_success_json($source['content'],'子系统查询成功');
        }
        rsp_die_json(10004, $source['message']);
    }

    public function addSource($post){
        $check_params_info = checkEmptyParams($post, ['source_name']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $source = $this->access->post('/source/add', $post);
        if($source['code']==0){
            rsp_success_json($source['content'],'子系统添加成功');
        }
        rsp_die_json(10004, $source['message']);
    }

    public function delSource($post){
        $check_params_info = checkParams($post, ['source_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $post['is_delete'] = 'Y';
        $source = $this->access->post('/source/update', $post);
        if($source['code']==0){
            rsp_success_json($source['content'],'子系统删除成功');
        }
        rsp_die_json(10004, $source['message']);
    }

    public function editSource($post){
        $check_params_info = checkParams($post, ['source_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $source = $this->access->post('/source/update', $post);
        if($source['code']==0){
            rsp_success_json($source['content'],'子系统编辑成功');
        }
        rsp_die_json(10004, $source['message']);
    }


}
