<?php
final class Modulepage extends Base
{
    /**
     * @param $post
     * 添加模块
     */
    public function addPrivlegesModule($post){
        $check_params_info = checkParams($post, ['source_id','module_name','pid']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $params = ['source_id' => $post['source_id'],
            'module_name'=>$post['module_name'],'pid'=>$post['pid']];
        $module_res = $this->access->post('/module/add',$params );
        if($module_res['code']==0){
            rsp_success_json('','模块添加成功');
        }
        rsp_die_json(10004, $module_res['message']);
    }


    /**
     * @param $post 获取模块
     */
    public function getModule($post){
        unsetEmptyParams($post);
        $check_params_info = checkEmptyParams($post, ['source_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $module_res = $this->access->post('/module/lists',$post );
        if($module_res['code']==0){
            rsp_success_json($module_res['content'],'模块查询成功');
        }
        rsp_die_json(10004, $module_res['message']);
    }
}