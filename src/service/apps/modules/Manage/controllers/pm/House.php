<?php

use  Project\SpaceModel;

final class House extends Base
{

    protected static $must_fields = [
        'project_id'=>'请选择所属项目',
        'space_id'=>'请选择空间',
        'charge_area'=>'请输入计费面积',
    ];

    public function lists ($params = [])
    {
        if (!isTrueKey($params,'page','pagesize')) rsp_error_tips(10001, "page、pagesize");
        $params['project_id'] = $this->project_id;
        if(isset($params['project_ids']) && $params['project_ids'] == 'all'){
            unset($params['project_id']);
        }
        $params = $this->getSearchIdsFromName($params);
        $house_ids = $this->getHouseIds($params);
        if( $house_ids ){
            $params['house_ids'] = $house_ids;
            unset($house_ids);
        }
        $data = $this->pm->post('/house/lists',array_merge($params,['is_paging'=>'Y']));
        if ($data['code'] !== 0) rsp_error_tips(10002);
        if (empty($data['content'])) rsp_success_json(['total' => 0,'lists' => []],$data['message']);

        $count = $this->pm->post('/house/count',$params);
        $num = $count['code'] == 0 ? $count['content'] : 0 ;

        $house_property = ['owner'=>[],'co'=>[]];
        foreach ($data['content'] as &$item){
            foreach ($item['house_property'] as $key=>$value){
                if($value['proprietor_type'] == 'owner'){
                    $house_property['owner'][] = $value;
                }else{
                    $house_property['co'][] = $value;
                }
            }
            $item['house_property'] = $house_property;
            $house_property = ['owner'=>[],'co'=>[]];
        }

        // 员工
        $employee_ids = array_unique(
            array_merge(
                array_filter(array_column($data['content'], 'created_by')),
                array_filter(array_column($data['content'], 'updated_by')),
                array_filter(array_column($data['content'], 'employee_id'))
            )
        );
        $employees = $this->user->post('/employee/lists',['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        // space
        $space_ids = array_unique(array_filter(array_column($data['content'], 'space_id')));
        $space_branches = $this->pm->post('/space/batchBranch', ['space_ids' => $space_ids]);
        $space_branches = $space_branches['content'] ?? [];

        $lists = array_map(function ($m) use ($employees, $space_branches) {
            $m['created_by'] = getArraysOfvalue($employees, $m['created_by'], 'full_name');
            $m['updated_by'] = getArraysOfvalue($employees, $m['updated_by'], 'full_name');
            $m['house_annex'] = json_decode($m['house_annex'],true);
            $m['employee_name'] = getArraysOfvalue($employees, $m['employee_id'], 'full_name');
            $m['employee_mobile'] = getArraysOfvalue($employees, $m['employee_id'], 'mobile');
            $branch_info = SpaceModel::parseBranch($space_branches[$m['space_id']] ?? []);
            $m['space_name_full'] = $branch_info['space_name_full'] ?? '';
            $m['houser_model'] = $m['houser_model'] ?: '';
            $m['house_user_type'] = $m['house_user_type'] ?: '';
            $m['house_attr'] = $m['house_attr'] ?: '';
            $m['houser_direction'] = $m['houser_direction'] ?: '';
            $m['house_uses'] = $m['house_uses'] ?: '';
            $m['house_situat'] = $m['house_situat'] ?: '';
            return $m;
        },$data['content']);

        rsp_success_json(['total' => $num,'lists' => $lists],'查询成功');
    }

    public function count ($params = [])
    {
        $params['project_id'] = $this->project_id;
        $params = $this->getSearchIdsFromName($params);
        $count = $this->pm->post('/house/count',$params);
        $num = $count['code'] == 0 ? $count['content'] : 0 ;

        rsp_success_json(['total' => $num],'查询成功');
    }

    /**
     * 获取 house_id 集合
     * @param $params
     * @return array
     */
    private function getHouseIds($params)
    {
        $query = [];
        $query['proprietor_name_f'] = $params['proprietor_name_f'] ?? null;
        if (isset($params['proprietor_mobile_f']) && mb_strlen($params['proprietor_mobile_f']) > 0) {
            if (!check_mobile($params['proprietor_mobile_f'])) {
                rsp_die_json(10001, '手机号格式错误,必须为纯数字,且不超过11位');
            }
            $query['proprietor_mobile_f'] = $params['proprietor_mobile_f'];
        }
        $query = array_filter($query, function ($m) {
            return !is_null($m) && $m !== '';
        });
        if (empty($query)) {
            return [];
        }
        $query['page'] = 1;
        $query['pagesize'] = 100;
        if(isset($params['project_id']) && $params['project_id']){
            $query['project_id'] = $params['project_id'];
        }
        $result = $this->pm->post('/house/property/lists', $query);
        if (!isset($result['code']) || $result['code'] != 0) {
            rsp_die_json(10002, '查询失败 '.($result['message'] ?: ''));
        }
        $house_ids = array_unique(array_filter(array_column($result['content'], 'house_id')));
        return $house_ids ?: ['888888888888888888888888'];
    }

    public function add($params = []){
        $info = [];

        foreach (self::$must_fields as $k=>$m){
            if(!isTruekey($params,$k)) rsp_error_tips(10002,$m);
            $info[$k] = trim($params[$k]);
        }

        $project = $this->pm->post('/project/show',['project_id'=>$info['project_id']]);
        if(empty($project['content'])){
            rsp_die_json(10002,'项目不存在');
        }

        $space = $this->pm->post('/space/show',['space_id'=>$info['space_id']]);
        if(empty($space['content'])){
            rsp_die_json(10002,'空间不存在');
        }else{
            if ($space['content']['space_type'] !== 1394) {
                rsp_die_json(10002,'请选择具体的房屋');
            }
        }

        if(isTruekey($params,'house_property')){
            $info['house_property'] = $params['house_property'];
        }else{
            rsp_error_tips(10001,'请填写房屋产权登记信息');
        }

        if(isset($params['remark']))$info['remark'] = $params['remark'];
        $info['house_room'] = $space['content']['space_name'];
        if(isset($params['house_collect_penalty']))$info['house_collect_penalty'] = $params['house_collect_penalty'];
        if(isset($params['employee_id']))$info['employee_id'] = $params['employee_id'];
        if(isset($params['house_annex'])){
            $info['house_annex'] = $params['house_annex'];
        }
        if(isTruekey($params,'house_model_pic')){
            $info['house_model_pic'] = is_array($params['house_model_pic']) ? json_encode($params['house_model_pic']) : $params['house_model_pic'];
        }

        $house_property = is_array($params['house_property'])?$params['house_property']:json_decode($params['house_property'], true);
        $house_cells = [];
        if(isTruekey($params,'house_cells')){
            $house_cells = is_array($params['house_cells'])?$params['house_cells']:json_decode($params['house_cells'], true);
        }

        foreach ($house_property as $item){
            if(isTrueKey($item,'proprietor_type') ){
                if($item['proprietor_type'] == 'owner'){
                    if(!isTrueKey($item,'proprietor_name')) rsp_error_tips(10001,'缺少业主名称');
                }
            }else{
                rsp_error_tips(10001,'缺少业主类型');
            }

            if(isTrueKey($item,'proprietor_mobile') && !isMobile($item['proprietor_mobile'])) rsp_error_tips(10007,'电话号码');
        }

        $check = [
            'project_id'=>$info['project_id'],
            'space_id'=>$info['space_id'],
            'house_room'=>$info['house_room']??'',
        ];
        $show = $this->pm->post('/house/show',$check);
        if($show['code'] == 0 && !empty($show['content'])) rsp_error_tips(10003);

        if($house_cells){
            foreach ($house_cells as &$v){
                $cell_id = resource_id_generator(self::RESOURCE_TYPES['cells']);
                if(!$cell_id)  rsp_error_tips(10001,'房间资源ID');
                $v['cell_id'] = $cell_id;
            }
            $info['house_cells'] = $house_cells;
        }

        $house_id = resource_id_generator(self::RESOURCE_TYPES['house']);
        if(!$house_id) rsp_error_tips(10001,'房产资源ID');
        $info['house_id'] = $house_id;

        // todo 当前登录用户ID
        $info['created_by'] = $info['updated_by'] = $this->employee_id;
        $info['project_id'] = $this->project_id;

        if(isset($params['houser_model']))$info['houser_model'] = $params['houser_model'];
        if(isset($params['charge_number']))$info['charge_number'] = $params['charge_number'];
        if(isset($params['live_number']))$info['live_number'] = $params['live_number'];
        if(isset($params['house_user_type']))$info['house_situat'] = $params['house_user_type'];
        if(isset($params['house_situat']))$info['house_situat'] = $params['house_situat'];
        if(isset($params['house_attr']))$info['house_attr'] = $params['house_attr'];
        if(isset($params['house_uses']))$info['house_uses'] = $params['house_uses'];
        if(isset($params['houser_direction']))$info['houser_direction'] = $params['houser_direction'];

        $result = $this->pm->post('/house/add',$info);
        if($result['code'] !=0 ) rsp_error_tips(10005,$result['message']);
        //添加审计日志
        Comm_AuditLogs::push(
            1331,
            $info['house_id'],
            '添加房产',
            1323,
            $info,
            '成功'
        );
        rsp_success_json($info['house_id'],'添加成功');
    }

    public function update($params = []){

        if(!isTrueKey($params,'house_id')) rsp_error_tips( 10001,'缺少房产信息');
        $info['house_id'] = $params['house_id'];

        foreach (self::$must_fields as $k=>$m){
            if(!isTruekey($params,$k)) rsp_error_tips(10001,$m);
            $info[$k] = trim($params[$k]);
        }

        $project = $this->pm->post('/project/show',['project_id'=>$info['project_id']]);
        if(empty($project['content'])){
            rsp_die_json(10002,'项目不存在');
        }

        $space = $this->pm->post('/space/show',['space_id'=>$info['space_id']]);
        if(empty($space['content'])){
            rsp_die_json(10002,'空间不存在');
        }else{
            if ($space['content']['space_type'] !== 1394) {
                rsp_die_json(10002,'请选择具体的房屋');
            }
        }

        if(isset($params['remark']))$info['remark'] = $params['remark'];
        $info['house_room'] = $space['content']['space_name'];
        if(isset($params['house_collect_penalty']))$info['house_collect_penalty'] = $params['house_collect_penalty'];
        if(isset($params['employee_id']))$info['employee_id'] = $params['employee_id'];
        if(isTruekey($params,'house_model_pic')){
            $info['house_model_pic'] = is_array($params['house_model_pic']) ? json_encode($params['house_model_pic']) : $params['house_model_pic'];
        }

        if(isTruekey($params,'house_property')){
            $info['house_property'] = $params['house_property'];
        }else{
            rsp_error_tips(10001,'请填写房屋产权登记信息');
        }

        if(isset($params['house_annex'])){
            $info['house_annex'] = $params['house_annex'];
        }

        if(isTruekey($params,'house_cells')){
            $house_cell = is_array($params['house_cells'])?$params['house_cells']:json_decode($params['house_cells'], true);
            if(isTrueKey($house_cell,'add')){
                foreach ($house_cell['add'] as &$v){
                    $cell_id = resource_id_generator(self::RESOURCE_TYPES['cells']);
                    if(!$cell_id)  rsp_error_tips(10001,'房间资源ID生成失败');
                    $v['cell_id'] = $cell_id;
                }
            }
            $info['house_cells'] = $house_cell;
        }

        // todo 当前登录用户ID
        $info['updated_by'] = $this->employee_id;
        $info['project_id'] = $this->project_id;
        if(isset($params['houser_model']))$info['houser_model'] = $params['houser_model'];
        if(isset($params['charge_number']))$info['charge_number'] = $params['charge_number'];
        if(isset($params['live_number']))$info['live_number'] = $params['live_number'];
        if(isset($params['house_user_type']))$info['house_situat'] = $params['house_user_type'];
        if(isset($params['house_situat']))$info['house_situat'] = $params['house_situat'];
        if(isset($params['house_attr']))$info['house_attr'] = $params['house_attr'];
        if(isset($params['house_uses']))$info['house_uses'] = $params['house_uses'];
        if(isset($params['houser_direction']))$info['houser_direction'] = $params['houser_direction'];
        
        $result = $this->pm->post('/house/update',$info);
        //添加审计日志
        Comm_AuditLogs::push(
            1331,
            $info['house_id'],
            '更新房产',
            1324,
            $info,
            (isset($result['code']) && $result['code'] == 0) ? '成功' : '失败'
        );
        if($result['code'] !=0 ) rsp_error_tips(10006,$result['message']);

        rsp_success_json($result['content'],'更新成功');
    }

    public function record_lists ($params = [])
    {
        if (!isTrueKey($params,'house_id')) rsp_error_tips(10001, "缺少房产信息");
        if (!isTrueKey($params,'record_type')) {
            $params['record_type'] = 'house';
        }
        $data = $this->pm->post('/record/lists',$params);
        if ($data['code'] !== 0) rsp_error_tips(10002,$data['message']);
        if (empty($data['content'])) rsp_success_json(['total' => 0,'lists' => []],$data['message']);

        $count = $this->pm->post('/record/count',$params);
        $num = $count['code'] == 0 ? $count['content'] : 0 ;

        foreach ($data['content'] as &$v){
            $v = json_decode($v['record_json'],true);
            $v['got_time'] = $v['got_time'] ? date('Y-m-d H:i:s',$v['got_time']) : '';
            $v['change_time'] = date('Y-m-d H:i:s',$v['change_time']);
            unset($v['creationtime'],$v['modifiedtime']);
        }

        rsp_success_json(['total' => $num,'lists' => $data['content']],'查询成功');
    }

    public function basic_lists ($params = [])
    {
        $params['project_id'] = $this->project_id;
        $params = $this->getSearchIdsFromName($params);
        if(empty($params)) rsp_error_tips(10001,'搜索参数不能为空');
        $data = $this->pm->post('/house/basic/lists',$params);
        if ($data['code'] !== 0) rsp_error_tips(10002,$data['message']);
        if (empty($data['content'])) rsp_success_json(['total' => 0,'lists' => []],$data['message']);

        $count = $this->pm->post('/house/count',$params);
        $num = $count['code'] == 0 ? $count['content'] : 0 ;

        rsp_success_json(['total' => $num,'lists' => $data['content']],'查询成功');
    }

    public function cells_lists ($params = [])
    {
        $params = $this->getSearchIdsFromName($params);
        if(empty($params)) rsp_error_tips(10001,'搜索参数不能为空');

        $data = $this->pm->post('/house/cells/lists',$params);
        if ($data['code'] !== 0) rsp_error_tips(10002,$data['message']);
        if (empty($data['content'])) rsp_success_json(['total' => 0,'lists' => []],$data['message']);

        $count = $this->pm->post('/house/cells/count',$params);
        $num = $count['code'] == 0 ? $count['content'] : 0 ;

        rsp_success_json(['total' => $num,'lists' => $data['content']],'查询成功');
    }

    public function getSearchIdsFromName($params){
        if(isTrueKey($params,'frame_id')){
            $tmp = $this->pm->post('/project/projects',['frame_id'=>$params['frame_id']]);
            if(empty($tmp['content'])) rsp_success_json(['total' => 0],'暂无数据');
            $project_ids = array_filter(array_unique(array_column($tmp['content'], 'project_id')));
            $params['project_ids'] = isset($params['project_ids']) ? array_merge($params['project_ids'],$project_ids) : $project_ids;
        }

        if(is_not_empty($params,'project_name')){
            $tmp = $this->pm->post('/project/projects',['project_name'=>$params['project_name'],'page'=>1,'pagesize'=>9999]);
            if(empty($tmp['content'])) rsp_success_json(['total' => 0],'暂无数据');
            $project_ids = array_filter(array_unique(array_column($tmp['content'], 'project_id')));
            $params['project_ids'] = isset($params['project_ids']) ? array_merge($params['project_ids'],$project_ids) : $project_ids;
        }

        if (isTrueKey($params, 'space_id')) {
            $space_ids = $this->pm->post('/space/children', ['space_id' => $params['space_id']]);
            $space_ids = ($space_ids['code'] === 0 && $space_ids['content']) ? $space_ids['content'] : [];
            if (!$space_ids) rsp_success_json(['total' => 0, 'lists' => []]);
            unset($params['space_id']);
            $params['space_ids'] = array_unique(array_filter(array_column($space_ids, 'space_id')));
        }
        return $params;
    }


    public function property_detail($params = [])
    {
        $params = $this->getSearchIdsFromName($params);
        if (empty($params)) rsp_error_tips(10001, '搜索参数不能为空');
        $data = $this->pm->post('/house/property/detail', $params);
        if ($data['code'] !== 0) rsp_error_tips(10002, $data['message']);
        if (empty($data['content'])) rsp_success_json(['total' => 0, 'lists' => []], $data['message']);
        $house_property = ['owner' => [], 'co' => []];
        foreach ($data['content'] as &$item) {
            foreach ($item['house_property'] as $key => $value) {
                if ($value['proprietor_type'] == 'owner') {
                    $house_property['owner'][] = $value;
                } else {
                    $house_property['co'][] = $value;
                }
            }
            $item['house_property'] = $house_property;
            $house_property = ['owner' => [], 'co' => []];
        }
        $lists = array_map(function ($m) {
            $branch = $this->pm->post('/space/branch', ['space_id' => $m['space_id']]);
            $branch = $branch['content'] ?? [];
            $branch_info = SpaceModel::parseBranch($branch, '-');
            $m = array_merge($m, $branch_info);
            $m['house_annex'] = json_decode($m['house_annex'], true);
            return $m;
        }, $data['content']);

        rsp_success_json(['total' => 1, 'lists' => $lists], '查询成功');
    }

}