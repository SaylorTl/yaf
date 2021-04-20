<?php

use Project\SpaceModel;

final class Visitor extends Base
{

    /**
 * @param array $post
 * @throws Exception
 * 访客列表
 */
    public function VisitorList($post=[])
    {
        unsetEmptyParams($post);
        if(!empty($_SESSION['member_project_id'])){
            $post['project_id'] = $_SESSION['member_project_id'];
        }
        if((isset($post['project_ids']) && $post['project_ids'] == 'all')){
            unset($post['project_id']);
        }

        $result = $this->user->post('/visitor/userlist',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }
        $lists = $result['content']['lists'];
        $sex_arr = array_column($lists,'sex');
        $appoint_status_arr = array_unique(array_filter(array_column($lists,'appoint_status_tag_id')));
        $tags = array_filter(array_merge($sex_arr,$appoint_status_arr));
        $tag_res = $this->tag->post('/tag/lists',['tag_ids'=>implode(',',$tags)]);
        if($tag_res['code']!=0){
            rsp_die_json(10002,$tag_res['message']);
        }
        $tag_content = array_column($tag_res['content'],null,'tag_id');
        $creator_arr = array_filter(array_unique(array_column($lists,'creator')));
        $editor_arr = array_filter(array_unique(array_column($lists,'editor')));
        $creator_res = $this->user->post('/employee/lists',['employee_ids'=>array_merge($creator_arr,$editor_arr)]);
        if($creator_res['code']!=0){
            rsp_die_json(10002,$creator_res['message']);
        }
        $creator_content =  array_column($creator_res['content'],null,'employee_id');

        $project_arr = array_unique(array_filter(array_column($lists,'project_id')));
        $project_res = $this->pm->post('/project/lists',['project_ids'=>$project_arr]);
        if($project_res['code']!=0){
            rsp_die_json(10002,$project_res['message']);
        }
        $project_content = array_column($project_res['content'],null,'project_id');

        // space
        $space_ids = array_unique(array_filter(array_column($lists, 'space_id')));
        $space_branches = $this->pm->post('/space/batchBranch', ['space_ids' => $space_ids]);
        $space_branches = $space_branches['content'] ?? [];

        foreach($result['content']['lists'] as $key=>$value){
            $result['content']['lists'][$key]['appoint_status_name'] = isset($tag_content[$value['appoint_status_tag_id']])?$tag_content[$value['appoint_status_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['in_time'] = !empty($value['in_time'])?date('Y-m-d H:i:s',$value['in_time']):'';
            $result['content']['lists'][$key]['out_time'] = !empty($value['out_time'])?date('Y-m-d H:i:s',$value['out_time']):'';
            $result['content']['lists'][$key]['appoint_time'] = !empty($value['appoint_time'])?date('Y-m-d H:i:s',$value['appoint_time']):'';
            $result['content']['lists'][$key]['creator'] = isset($creator_content[$value['creator']])?$creator_content[$value['creator']]['full_name']:'';
            $result['content']['lists'][$key]['editor'] = isset($creator_content[$value['editor']])?$creator_content[$value['editor']]['full_name']:'';
            $result['content']['lists'][$key]['project_name'] =isset($project_content[$value['project_id']])?$project_content[$value['project_id']]['project_name']:'';
            $branch_info = SpaceModel::parseBranch($space_branches[$result['content']['lists'][$key]['space_id']] ?? []);
            $result['content']['lists'][$key]['space_name_full'] = $branch_info['space_name_full'] ?? '';
        }
        rsp_success_json($result['content'],'查询成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 访客添加
     */
    public function VisitorAdd($post=[])
    {
        $check_params_info = checkEmptyParams($post, ['real_name','sex','mobile','space_id','authorizer']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $visit_res = $this->resource->post('/resource/id/generator',['type_name'=>'visitor']);
        if($visit_res['code']!=0 || ($visit_res['code']==0 && empty($visit_res['content']))){
            rsp_die_json(10001, $visit_res['message']);
        }
        if(!empty($_SESSION['member_project_id'])){
            $post['project_id'] = $_SESSION['member_project_id'];
        }
        $post['visit_id'] = $visit_res['content'];
        $post['creator'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
        $post['editor'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
        $result = $this->user->post('/visitor/useradd',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        //添加审计日志
        Comm_AuditLogs::push(1341, $result['content'], '添加访客记录', 1323, $post, '成功');
        rsp_success_json($result['content'],'添加成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 访客附加信息展示
     */
    public function Visitorextlist($post=[])
    {
        $result = $this->user->post('/visitor/extlist',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,'查询失败');
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json(['car_list'=>[],'follow_list'=>[],'label_list'=>[]],'查询成功');
        }
        rsp_success_json($result['content'],'查询成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 访客添加
     */
    public function VisitorUpdate($post=[])
    {
        $check_params_info = checkParams($post, ['visit_id','real_name','sex','mobile','space_id','authorizer']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        if(empty($_SESSION['member_project_id'])){
            $post['project_id'] = $_SESSION['member_project_id'];
        }
        $post['editor'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
        $result = $this->user->post('/visitor/userupdate',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        //添加审计日志
        Comm_AuditLogs::push(
            1341,
            $post['visit_id'],
            '更新访客记录',
            1324,
            $post,
            (isset($result['code']) && $result['code'] == 0) ? '成功' : '失败'
        );

        //事件触发器推送
        $result = Comm_EventTrigger::push('wos_trail_user_update', ['visit_id' => $post['visit_id']]);
        if (empty($result)) {
            info(__METHOD__, ['error' => '访客更新事件触发器推送失败', 'visit_id' => $post['visit_id']]);
        }
        rsp_success_json($result['content'],'更新成功');
    }

}

