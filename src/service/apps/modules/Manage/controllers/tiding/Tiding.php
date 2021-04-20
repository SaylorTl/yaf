<?php

final class Tiding extends Base
{
    public function lists($params = [])
    {
        if (!isTrueKey($params, 'page', 'pagesize')) rsp_error_tips(10001, "page、pagesize");
        if(isTrueKey($params,'subapp')){
            $scope = $this->getScope(['scope'=>$params['subapp']],'show');
            if( $scope['code'] != 0 || empty($scope['content'])){
                rsp_success_json(['count'=>0,'rows'=>[]], '无数据');
            }
            $params['subapp'] = $scope['content']['scope_id'];
        }

        if(isTrueKey($params,'begin_create_at')){
            $params['create_at']['begin'] =  $params['begin_create_at'];
        }

        if(isTrueKey($params,'end_create_at')){
            $params['create_at']['end'] =  $params['end_create_at'];
        }

        if(isset($params['create_at'])) $params['create_at'] = json_encode($params['create_at']);

        if(isTrueKey($params,'initiator')){
            if(is_array($params['initiator'])){
                if(!isset($params['initiator']['s'])) rsp_success_json(['count'=>0,'rows'=>[]], '无数据!');
                $params['initiator'] = $params['initiator']['s'];
            }else if(!is_not_json($params['initiator'])){
                $initiator = json_decode($params['initiator'],true);
                if(!isset($initiator['s'])) rsp_success_json(['count'=>0,'rows'=>[]], '无数据!!');
                $params['initiator'] = $initiator['s'];
            }else{
                rsp_success_json(['count'=>0,'rows'=>[]], '无数据!!!');
            }
            if(is_array($params['initiator'])) $params['initiator'] = $params['initiator'][0];
        }

        $audience = [];
        $audience['s'] = $this->employee_id;
        $emp = $this->user->post('/employee/userlist',['employee_id'=>$this->employee_id]);
        if($emp['code']==0 && !empty($emp['content'])){
            $audience['e'] = $emp['content']['lists'][0]['frame_id']; 
        }
        $params['audience'] = json_encode($audience);

        $data = $this->tiding->post('/tiding/lists', $params);
        if ($data['code'] !== 0) rsp_error_tips(10002,$data['message']);
        if (empty($data['content']) || empty($data['content']['rows'])) rsp_success_json([], '没有数据');

        // 员工
        $employee_ids = array_unique(array_filter(array_column($data['content']['rows'], 'initiator')));
        $employees = $this->user->post('/employee/lists', ['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        //子应用
        $subapp_ids = array_unique(array_filter(array_column($data['content']['rows'], 'subapp')));
        $subapps = $this->getScope(['scope_ids'=>$subapp_ids,'not_limit_page'=>'Y'],'lists');
        $subapps = ($subapps['code'] == 0 && $subapps['content']) ? many_array_column($subapps['content']['lists'], 'scope_id') : [];

        $kind = $this->kind;
        $lists = array_map(function ($m) use (  $employees,$kind,$subapps) {
            $m['initiator_name'] = getArraysOfvalue($employees, $m['initiator'], 'full_name');
            $m['kind_name'] = $kind[$m['kind']] ?? '';
            $m['source_name'] = getArraysOfvalue($subapps, $m['subapp'], 'remark');
            $m['create_at'] = date('Y-m-d H:i',strtotime($m['create_at']));
            $m['update_at'] = date('Y-m-d H:i:s',strtotime($m['update_at']));
            return $m;
        }, $data['content']['rows']);

        rsp_success_json(['count'=>$data['content']['count'],'rows'=>$lists], '查询成功');
    }

    public function show($params = [])
    {
        $data = $this->tiding->post('/tiding/show', $params);
        if ($data['code'] !== 0) rsp_error_tips(10002,$data['message']);
        if (empty($data['content']) ) rsp_success_json([], '没有数据');

        // 员工
        $employee_ids = array_unique(array_filter(array_column([$data['content']], 'initiator')));
        $employees = $this->user->post('/employee/lists', ['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        //子应用
        $subapp_ids = array_unique(array_filter(array_column([$data['content']], 'subapp')));
        $subapps = $this->getScope(['scope_ids'=>$subapp_ids,'not_limit_page'=>'Y'],'lists');
        $subapps = ($subapps['code'] == 0 && $subapps['content']) ? many_array_column($subapps['content']['lists'], 'scope_id') : [];

        $kind = $this->kind;
        $lists = array_map(function ($m) use (  $kind,$employees,$subapps) {
            $m['initiator_name'] = getArraysOfvalue($employees, $m['initiator'], 'full_name');
            $m['kind_name'] = $kind[$m['kind']] ?? '';
            $m['source_name'] = getArraysOfvalue($subapps, $m['subapp'], 'remark');
            $m['create_at'] = date('Y-m-d H:i',strtotime($m['create_at']));
            $m['update_at'] = date('Y-m-d H:i:s',strtotime($m['update_at']));
            return $m;
        }, [$data['content']]);

        //将消息标记为已读状态
        if(isset($data['content']['tags'])){
            $tags = $data['content']['tags'];
            if(empty($tags)){
                $tag_info = ['tiding_id'=>$data['content']['_id'],'tags'=>['read'=>[$this->employee_id]]];
            }else{
                $tags_arr =  array_unique(array_filter(array_merge($tags['read'],[$this->employee_id])));
                $tag_info = ['tiding_id'=>$data['content']['_id'],'tags'=>['read'=>$tags_arr]];
            }
            $this->tiding->post('/tiding/update',$tag_info);
        }

        rsp_success_json(['count'=>1,'rows'=>$lists], '查询成功');
    }

}