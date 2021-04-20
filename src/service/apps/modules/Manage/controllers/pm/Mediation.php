<?php

use Project\SpaceModel;

final class Mediation extends Base
{
    public function lists ($params = [])
    {
        if (!isTrueKey($params,'page','pagesize')) rsp_error_tips(10001, "page、pagesize");
        $params['project_id'] = $this->project_id;
        $data = $this->pm->post('/mediation/lists',$params);
        if ($data['code'] !== 0) rsp_error_tips(10002);
        if (empty($data['content'])) rsp_success_json(['total' => 0,'lists' => []],$data['message']);

        $count = $this->pm->post('/mediation/count',$params);
        $num = $count['code'] == 0 ? $count['content'] : 0 ;

        $project_ids = array_filter(array_unique(array_column($data['content'], 'project_id')));
        $tmp = $this->pm->post('/project/projects',['project_ids'=>$project_ids]);
        $projects = $tmp ? many_array_column($tmp['content'], 'project_id') : [];

        // space
        $space_ids = array_unique(array_filter(array_column($data['content'], 'space_id')));
        $space_branches = $this->pm->post('/space/batchBranch', ['space_ids' => $space_ids]);
        $space_branches = $space_branches['content'] ?? [];

        // 员工
        $created_by = array_filter(array_column($data['content'], 'created_by'));
        $updated_by = array_filter(array_column($data['content'], 'updated_by'));
        $employee_id = array_filter(array_column($data['content'], 'employee_id'));
        $employee_ids = array_unique(array_merge($created_by, $updated_by,$employee_id));
        $employees = $this->user->post('/employee/lists',['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        $lists = array_map(function ($m) use ($projects,$employees,$space_branches) {
            $m['project_name'] = getArraysOfvalue($projects, $m['project_id'], 'project_name');
            $m['employee_name'] = getArraysOfvalue($employees, $m['employee_id'], 'full_name');
            $m['employee_mobile'] = getArraysOfvalue($employees, $m['employee_id'], 'mobile');
            $m['media_annex'] = json_decode($m['media_annex']);
            $m['created_by'] = getArraysOfvalue($employees, $m['created_by'], 'full_name');
            $m['updated_by'] = getArraysOfvalue($employees, $m['updated_by'], 'full_name');
            $branch_info = SpaceModel::parseBranch($space_branches[$m['space_id']] ?? []);
            $m['space_name_full'] = $branch_info['space_name_full'] ?? '';
           return $m;
        },$data['content']);

        rsp_success_json(['total' => $num,'lists' => $lists],'查询成功');
    }

    public function add($params = []){
        $info = [];

        if(isTruekey($params,'project_id')){
            $info['project_id'] = $params['project_id'];
        }else{
            rsp_error_tips(10001,'所属项目');
        }

        if(isset($params['space_id'])){
            $info['space_id'] = $params['space_id'];
        }else{
            rsp_error_tips(10001,'空间');
        }

        if(isTruekey($params,'house_status')){
            $info['house_status'] = $params['house_status'];
        }else{
            rsp_error_tips(10001,'房源状态');
        }

        if(is_not_empty($params,'landlord_name')){
            $info['landlord_name'] = $params['landlord_name'];
        }else{
            rsp_error_tips(10001,'房东姓名');
        }

        if(is_not_empty($params,'landlord_mobile')){
            if(!isMobile($params['landlord_mobile'])) rsp_error_tips(10007,'手机号码');
            $info['landlord_mobile'] = $params['landlord_mobile'];
        }

        $field = [
            'house_price','time_slot','employee_id','media_annex','media_describe','media_remark', 'space_id',
        ];

        $info = initParams($params,$field,$info);

        $check = ['project_id'=>$info['project_id'],'space_id'=>$info['space_id']];
        $show = $this->pm->post('/mediation/show',$check);
        if($show['code'] == 0 && !empty($show['content'])) rsp_error_tips(10003);

        $media_id = resource_id_generator(self::RESOURCE_TYPES['mediation']);
        if(!$media_id) rsp_error_tips(10014,'资源ID');
        $info['media_id'] = $media_id;

        // todo 当前登录用户ID
        $info['created_by'] = $info['updated_by'] = $this->employee_id;
        $info['project_id'] = $this->project_id;

        $result = $this->pm->post('/mediation/add',$info);
        if($result['code'] !=0 ) rsp_error_tips(10005);

        //添加审计日志
        Comm_AuditLogs::push(1332, $media_id, '添加房屋中介', 1323, $info, '成功');
        rsp_success_json($info['media_id'],'添加成功');
    }

    public function update($params = []){
        if(!isTrueKey($params,'media_id')) rsp_error_tips(10001,' media_id');
        $info['media_id'] = $params['media_id'];
        if(isTruekey($params,'project_id')){
            $info['project_id'] = $params['project_id'];
        }else{
            rsp_error_tips(10001,'所属项目');
        }

        if(isTruekey($params,'space_id')){
            $info['space_id'] = $params['space_id'];
        }else{
            rsp_error_tips(10001,'空间');
        }

        if(is_not_empty($params,'landlord_name')){
            $info['landlord_name'] = $params['landlord_name'];
        }

        if(is_not_empty($params,'landlord_mobile')){
            if(!isMobile($params['landlord_mobile'])) rsp_error_tips(10007,'手机号码');
            $info['landlord_mobile'] = $params['landlord_mobile'];
        }

        if(isTruekey($params,'house_status')){
            $info['house_status'] = $params['house_status'];
        }

        $field = [
            'house_price','time_slot','employee_id','media_annex','media_describe','media_remark', 'space_id',
        ];

        $info = initParams($params,$field,$info);

        $check = [
            'project_id'=>$info['project_id'],'space_id'=>$info['space_id'], 'not_media_id'=>$info['media_id']
        ];

        $show = $this->pm->post('/mediation/show',$check);
        if($show['code'] == 0 && !empty($show['content'])) rsp_error_tips(10003);

        // todo 当前登录用户ID
        $info['updated_by'] = $this->employee_id;
        $info['project_id'] = $this->project_id;
        $result = $this->pm->post('/mediation/update',$info);
        //添加审计日志
        Comm_AuditLogs::push(
            1332,
            $info['media_id'],
            '更新房屋中介',
            1324,
            $info,
            (isset($result['code']) && $result['code'] == 0) ? '成功' : '失败'
        );
        if($result['code'] !=0 ) rsp_error_tips(10006);

        rsp_success_json($result['content'],'更新成功');
    }



}