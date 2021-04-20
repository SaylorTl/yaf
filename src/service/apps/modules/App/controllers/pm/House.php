<?php

use Project\SpaceModel;

final class House extends Base
{

    protected static $must_fields = [
        'project_id'=>'请选择所属项目',
        'live_number'=>'请输入居住人数',
        'space_id'=>'请选择空间',
        'charge_area'=>'请输入计费面积',
        'charge_number'=>'请输入计费人数',
        'houser_direction'=>'请选择朝向',
        'houser_model'=>'请选择户型',
        'house_situat'=>'请选择使用情况',
        'house_uses'=>'请选择实际用途',
        'house_user_type'=>'请选择使用类别',
        'house_attr'=>'请选择使用属性'
    ];

    const prefix = 'house_update';
    const redis_db = 8;

    public function lists ($params = [])
    {
        if (!isTrueKey($params,'page','pagesize')) rsp_error_tips(10001, "page、pagesize");

        $params = $this->getSeachIdsFromName($params);

        $data = $this->pm->post('/house/lists',$params);
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
        $employee_ids = array_unique(array_merge(array_filter(array_column($data['content'], 'created_by')), array_filter(array_column($data['content'], 'updated_by'))));
        $employees = $this->user->post('/employee/lists',['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        $lists = array_map(function ($m) use ($employees) {
            $m['created_by'] = getArraysOfvalue($employees, $m['created_by'], 'full_name');
            $m['updated_by'] = getArraysOfvalue($employees, $m['updated_by'], 'full_name');
            $m['house_annex'] = json_decode($m['house_annex'],true);
            return $m;
        },$data['content']);

        rsp_success_json(['total' => $num,'lists' => $lists],'查询成功');
    }

    public function count ($params = [])
    {
        $params = $this->getSeachIdsFromName($params);

        $count = $this->pm->post('/house/count',$params);
        $num = $count['code'] == 0 ? $count['content'] : 0 ;

        rsp_success_json(['total' => $num],'查询成功');
    }


    public function add($params = []){
        $info = [];

        foreach (self::$must_fields as $k=>$m){
            if(!isTruekey($params,$k)) rsp_error_tips(10002,$m);
            $info[$k] = trim($params[$k]);
        }

        if(isTruekey($params,'house_property')){
            $info['house_property'] = $params['house_property'];
        }else{
            rsp_error_tips(10001,'请填写房屋产权登记信息');
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
                    if(!isTrueKey($item,'proprietor_mobile')) rsp_error_tips(10001,'缺少联系电话');
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

        $result = $this->pm->post('/house/add',$info);
        if($result['code'] !=0 ) rsp_error_tips(10005);

        rsp_success_json($info['house_id'],'添加成功');
    }

    public function update($params = []){

        if(!isTrueKey($params,'house_id')) rsp_error_tips( 10001,'缺少房产信息');
        $info['house_id'] = $params['house_id'];

        foreach (self::$must_fields as $k=>$m){
            if(isTruekey($params,$k)){
                $info[$k] = trim($params[$k]);
            }
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
        if(isTruekey($params,'house_model_pic')){
            $info['house_model_pic'] = is_array($params['house_model_pic']) ? json_encode($params['house_model_pic']) : $params['house_model_pic'];
        }

        if(isTruekey($params,'house_property')){
            $info['house_property'] = $params['house_property'];
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

        $result = $this->pm->post('/house/update',$info);
        if($result['code'] !=0 ) rsp_error_tips(10006,$result['message']);

        $this->set_updated($info['house_id']);

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
        $params = $this->getSeachIdsFromName($params);
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
        $params = $this->getSeachIdsFromName($params);
        if(empty($params)) rsp_error_tips(10001,'搜索参数不能为空');

        $data = $this->pm->post('/house/cells/lists',$params);
        if ($data['code'] !== 0) rsp_error_tips(10002,$data['message']);
        if (empty($data['content'])) rsp_success_json(['total' => 0,'lists' => []],$data['message']);

        $count = $this->pm->post('/house/cells/count',$params);
        $num = $count['code'] == 0 ? $count['content'] : 0 ;

        rsp_success_json(['total' => $num,'lists' => $data['content']],'查询成功');
    }

    public function getSeachIdsFromName($params){
        if(isTrueKey($params,'frame_id')){
            $tmp = $this->pm->post('/project/projects',['frame_id'=>$params['frame_id']]);
            if(empty($tmp['content'])) rsp_success_json(['total' => 0],'暂无数据');
            $project_ids = array_filter(array_unique(array_column($tmp['content'], 'project_id')));
            $params['project_ids'] = isset($params['project_ids']) ? array_merge($params['project_ids'],$project_ids) : $project_ids;
        }

        if(is_not_empty($params,'project_name')){
            $tmp = $this->pm->post('/project/projects',['project_name'=>$params['project_name'],'page'=>1,'pagesize'=>9999 ]);
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


    public function basic_tree($params = []){
        $params = $this->getSeachIdsFromName($params);
        if(empty($params)) rsp_error_tips(10001,'搜索参数不能为空');

        $data = $this->pm->post('/house/basic/lists',$params);
        if ($data['code'] !== 0) rsp_error_tips(10002,$data['message']);
        if (empty($data['content'])) rsp_success_json(['total' => 0,'lists' => []],$data['message']);

        $space_data = []; $house_data = []; $house_units = []; $house_floors = []; 
        $space_ids = array_unique(array_column($data['content'],'space_id'));
        foreach($space_ids as $space_id){
            foreach($data['content'] as $v){
                if($v['space_id'] == $space_id){
                    $space_name = $v['space_name'];
                    $house_data[$space_id]['data']['house_rooms'][]= [
                        'house_room' => $v['house_room'],
                        'space_id' => $v['space_id'],
                        'house_id' => $v['house_id'],
                    ];
                    if(!empty($v['house_floor']) ) array_push($house_floors,$v['house_floor']);
                    if(!empty($v['house_unit']) ) array_push($house_units,$v['house_unit']);
                }
            }
            $house_data[$space_id]['space_id'] = $space_id;
            if(!empty($house_floors)) {
                $house_floors = array_unique($house_floors);
                asort($house_floors,SORT_STRING );
            }
            if(!empty($house_units)){
               $house_units = array_unique($house_units);
               asort($house_units,SORT_STRING ); 
            }
            $house_data[$space_id]['data']['house_units'] = array_values($house_units);

            $house_data[$space_id]['data']['house_floors'] = array_values($house_floors);
            $house_data[$space_id]['data']['house_floor_data'] = [];
            $house_data[$space_id]['data']['house_unit_data'] = [];
            $space_data[] = ['space_id'=>$space_id,'space_name'=>$space_name ];
        }
        $house_floor_data = []; $house_unit_data = [];
        foreach($house_floors as $house_floor){
            foreach($data['content'] as $v){
                if($v['house_floor'] == $house_floor){
                    $space_id = $v['space_id'];
                    $house_floor_data[$house_floor][] = [
                        'house_room' => $v['house_room'],
                        'space_id' => $v['space_id'],
                        'house_id' => $v['house_id'],
                        'house_floor' => $v['house_floor'],
                    ];
                }
            }
            $house_data[$space_id]['data']['house_floor_data'] = $house_floor_data;
        }

        foreach($house_units as $house_unit){
            foreach($data['content'] as $v){
                if($v['house_unit'] == $house_unit){
                    $space_id = $v['space_id'];
                    $house_unit_data[$house_unit][] = [
                        'house_room' => $v['house_room'],
                        'space_id' => $v['space_id'],
                        'house_id' => $v['house_id'],
                    ];
                }
            }
            $house_data[$space_id]['data']['house_unit_data']= $house_unit_data;
        }
        if(!empty($house_data) ) $house_data = array_values($house_data);
        $data = [
            'space_data'=>$space_data,
            'house_data'=>$house_data,
        ];

        rsp_success_json($data,'success');

    }

    public function property_detail($params = []){
        $params = $this->getSeachIdsFromName($params);
        if(empty($params)) rsp_error_tips(10001,'搜索参数不能为空');
        $data = $this->pm->post('/house/property/detail',$params);
        if ($data['code'] !== 0) rsp_error_tips(10002,$data['message']);
        if (empty($data['content'])) rsp_success_json(['total' => 0,'lists' => []],$data['message']);
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
        $lists = array_map(function ($m){
            $m['house_annex'] = json_decode($m['house_annex'],true);
            return $m;
        },$data['content']);

        $tenement = $this->user->post('/tenement/lists', ['page' => 1, 'pagesize' => 1, 'user_id' => $_SESSION['user_id'], 'project_id' => $lists[0]['project_id']]);
        $tenement = $tenement['content'][0] ?? [];
        if (!$tenement) {
            $lists[0]['client_house_status'] = 'N';
        } else {
            $tenement_house = $this->user->post('/house/lists', ['page' => 1, 'pagesize' => 1, 'tenement_id' => $tenement['tenement_id'], 'house_id' => $lists[0]['house_id']]);
            $tenement_house = $tenement_house['content'][0] ?? [];
            $lists[0]['tenement_house_status'] = $tenement_house['tenement_house_status'] ?? 'N';
        }
        $lists = array_map(function ($m) {
            $branch = $this->pm->post('/space/branch', ['space_id' => $m['space_id']]);
            $branch = $branch['content'] ?? [];
            $branch_info = SpaceModel::parseBranch($branch, '-');
            $m = array_merge($m, $branch_info);
            return $m;
        }, $lists);
        rsp_success_json(['total' => 1,'lists' => $lists],'查询成功');
    }

    public function is_updated($params = []){
        if(isTrueKey($params,'house_id') == false) rsp_error_tips(10001,'house_id');
        $show = $this->pm->post('/house/property/detail',['house_id'=>$params['house_id']]);
        if($show['code'] == 0 && !empty($show['content']) ) {
            if(!empty($show['content'][0]['house_property'])){
                $key = self::prefix;
                $redis = Comm_Redis::getInstance();
                $redis->select(self::redis_db);
                $res = $redis->hget($key,$params['house_id']);
                if($res) rsp_error_tips(10016,'无法编辑');
                rsp_success_json(1,'可以编辑');
            }
        }
        rsp_success_json(2,'可以编辑');
    }

    public function set_updated($house_id){
        if(!$house_id) return false;
        $key = self::prefix;
        $redis = Comm_Redis::getInstance();
        $redis->select(self::redis_db);
        $redis->hset($key,$house_id,1);
    }

}