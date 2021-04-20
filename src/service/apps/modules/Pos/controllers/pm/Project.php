<?php

use Project\ConstantModel as Constant;

final class Project extends Base
{
    public function lists($params = [])
    {
        if (!isTrueKey($params, 'page', 'pagesize')) rsp_die_json(10001, 'page pagesize 参数缺失或错误');

        $lists = $this->pm->post('/project/lists', $params);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        unset($params['page'], $params['pagesize']);
        $total = $this->pm->post('/project/count', $params);
        if ($total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => 0, 'lists' => $lists['content']]);

        // 区域
        $frame_ids = array_unique(array_filter(array_column($lists['content'], 'frame_id')));
        $frames = $this->pm->post('/framelists', ['frame_ids' => implode(',', $frame_ids)]);
        $frames = ($frames['code'] === 0 && $frames['content']) ? many_array_column($frames['content'], 'frame_id') : [];

        // 公司
        $company_ids = array_unique(array_merge(array_filter(array_column($lists['content'], 'property_company_id')), array_filter(array_column($lists['content'], 'developer_company_id'))));
        $companies = $this->company->post('/corporate/lists', ['company_ids' => $company_ids]);
        $companies = ($companies['code'] === 0 && $companies['content']) ? many_array_column($companies['content'], 'company_id') : [];

        // 员工
        $employee_ids = array_unique(array_merge(array_filter(
            array_column($lists['content'], 'created_by')),
            array_filter(array_column($lists['content'], 'updated_by')),
            array_filter(array_column($lists['content'], 'charge_employee_id'))
        ));
        $employees = $this->user->post('/employee/lists',['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        rsp_success_json(['total' => (int)$total['content'], 'lists' => array_map(function ($m) use ($frames, $companies, $employees) {
            $m['frame_name'] = getArraysOfvalue($frames, $m['frame_id'], 'frame_name');
            $m['property_company_name'] = getArraysOfvalue($companies, $m['property_company_id'], 'company_name');
            $m['developer_company_name'] = getArraysOfvalue($companies, $m['developer_company_id'], 'company_name');
            $m['created_by'] = getArraysOfvalue($employees, $m['created_by'], 'full_name');
            $m['updated_by'] = getArraysOfvalue($employees, $m['updated_by'], 'full_name');
            $m['charge_person'] = getArraysOfvalue($employees, $m['charge_employee_id'], 'full_name');
            return $m;
        }, $lists['content'])]);
    }

    public function projects($params = [])
    {
        $lists = $this->pm->post('/project/projects', $params);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        unset($params['page'], $params['pagesize']);
        $total = $this->pm->post('/project/count', $params);
        if ($total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => 0, 'lists' => $lists['content']]);

        rsp_success_json(['total' => (int)$total['content'], 'lists' => $lists['content']]);
    }

    public function show($params = [])
    {
        $show = $this->pm->post('/project/show', $params);
        if ($show['code'] !== 0 || !$show['content']) rsp_success_json([]);

        rsp_success_json($show['content']);
    }

    public function add($params = [])
    {
        $fields = [
            'project_name', 'property_company_id', 'developer_company_id', 'frame_id', 'status_tag_id',
            'manage_type_tag_id', 'charge_type_tag_id', 'service_type_tag_id', 'guard_type_tag_id', 'cleaning_type_tag_id', 'has_canteen_tag_id', 'has_dorm_tag_id', 'household_num_tag_id',
            'country_id', 'province_id', 'city_id', 'region_id', 'address_detail', 'location',
            'project_types','project_files',
        ];
        if ($diff_fields = get_empty_fields($fields, $params)) rsp_die_json(10001, '缺少参数 '.implode(' ', $diff_fields));
        switch ((int)$params['status_tag_id']) {
            case 5:     //前期服务
                if (!isTrueKey($params, 'status_pre_begin_at', 'status_pre_end_at')) rsp_die_json(10001, '项目时间不能为空');
                break;
            case 6:     // 运营
                if (!isTrueKey($params, 'status_ing_begin_at', 'status_ing_end_at')) rsp_die_json(10001, '项目时间不能为空');
                break;
            case 7:     // 撤场
                if (!isTrueKey($params, 'status_retreat_begin_at')) rsp_die_json(10001, '项目时间不能为空');
                break;
            default:
                break;
        }
        foreach (json_decode($params['project_types'] ?? '', true) ?: [] as $item){
            if (!isTrueKey($item, 'type_id')) rsp_die_json(10001, '项目类型不能为空');
        }

        $params['project_agreements'] = json_encode(array_filter(array_map(function ($m) {
            return get_empty_fields(['agreement_id'], $m) === ['agreement_id'] ? null : $m;
        }, json_decode($params['project_agreements'] ?? '', true) ?: [])));

        $checkHoas = Constant::HOAS;
        $params['project_hoas'] = json_encode(array_filter(array_map(function ($m) use($checkHoas) {
            return get_empty_fields($checkHoas, $m) === $checkHoas ? null : $m;
        }, json_decode($params['project_hoas'] ?? '', true) ?: [])));

        $params['project_phases'] = json_encode(array_filter(array_map(function ($m) {
            return get_empty_fields(['phase_name'], $m) === ['phase_name'] ? null : $m;
        }, json_decode($params['project_phases'] ?? '', true) ?: [])));

        $checkFiles = Constant::FILES;
        $params['project_files'] = json_encode(array_filter(array_map(function ($m) use($checkFiles){
            return get_empty_fields($checkFiles, $m) === $checkFiles ? null : $m;
        }, json_decode($params['project_files'] ?? '', true) ?: [])));

        $params['project_id'] = resource_id_generator(self::RESOURCE_TYPES['project']);
        if (!$params['project_id']) rsp_die_json(10001, '生成资源ID失败');

        // todo 添加到用户微服务


        $params['created_at'] =  $params['updated_at'] = time();
        $params['created_by'] =  $params['updated_by'] = $this->employee_id;

        $result = $this->pm->post('/project/add', $params);
        if ($result['code'] !== 0) rsp_die_json(10001, $result['message']);
        rsp_success_json($result['content']);
    }

    public function update($params = [])
    {
        $fields = [
            'project_id', 'project_name', 'property_company_id', 'developer_company_id', 'frame_id', 'status_tag_id',
            'manage_type_tag_id', 'charge_type_tag_id', 'service_type_tag_id', 'guard_type_tag_id', 'cleaning_type_tag_id', 'has_canteen_tag_id', 'has_dorm_tag_id', 'household_num_tag_id',
            'country_id', 'province_id', 'city_id', 'region_id', 'address_detail', 'location',
            'project_types'
        ];
        if ($diff_fields = get_empty_fields($fields, $params)) rsp_die_json(10001, '缺少参数 '.implode(' ', $diff_fields));
        switch ((int)$params['status_tag_id']) {
            case 5:     //前期服务
                if (!isTrueKey($params, 'status_pre_begin_at', 'status_pre_end_at')) rsp_die_json(10001, '项目时间不能为空');
                break;
            case 6:     // 运营
                if (!isTrueKey($params, 'status_ing_begin_at', 'status_ing_end_at')) rsp_die_json(10001, '项目时间不能为空');
                break;
            case 7:     // 撤场
                if (!isTrueKey($params, 'status_retreat_begin_at')) rsp_die_json(10001, '项目时间不能为空');
                break;
            default:
                break;
        }
        foreach (json_decode($params['project_types'] ?? '', true) ?: [] as $item){
            if (!isTrueKey($item, 'type_id')) rsp_die_json(10001, '项目类型不能为空');
        }

        $params['project_agreements'] = json_encode(array_filter(array_map(function ($m) {
            return get_empty_fields(['agreement_id'], $m) === ['agreement_id'] ? null : $m;
        }, json_decode($params['project_agreements'] ?? '', true) ?: [])));

        $checkHoas = Constant::HOAS;
        $params['project_hoas'] = json_encode(array_filter(array_map(function ($m) use($checkHoas){
            return get_empty_fields($checkHoas, $m) === $checkHoas ? null : $m;
        }, json_decode($params['project_hoas'] ?? '', true) ?: [])));

        $params['project_phases'] = json_encode(array_filter(array_map(function ($m) {
            return get_empty_fields(['phase_name'], $m) === ['phase_name'] ? null : $m;
        }, json_decode($params['project_phases'] ?? '', true) ?: [])));

        $checkFiles = Constant::FILES;
        $project_files = is_array($params['project_files'])
            ? $params['project_files'] : json_decode($params['project_files'],true);
        $params['project_files'] = json_encode(array_filter(array_map(function ($m) use($checkFiles){
            return get_empty_fields($checkFiles, $m) === $checkFiles ? null : $m;
        }, $project_files)));

        $params['updated_at'] = time();
        $params['updated_by'] = $this->employee_id;

        $result = $this->pm->post('/project/update', $params);
        if ($result['code'] !== 0) rsp_die_json(10001, $result['message']);
        rsp_success_json($result['content']);
    }

    public function relevanceDetails($params = [])
    {
        if (false == isTrueKey($params, 'project_id')) {
            rsp_die_json(10001, '缺少项目编号信息');
        }

        $default_result = [
            'project_agreements'=>[],
            'project_files'=>[],
            'project_hoas'=>[]
        ];

        $params['page'] = 1;
        $params['pagesize'] = 1;
        $lists = $this->pm->post('/project/lists', $params);
        $content = $lists['content'][0] ?? [];
        if ($lists['code'] !== 0 || empty($content)) {
            rsp_success_json($default_result,'查询成功');
        }

        // 合同
        if( $content['project_agreements'] ){
            $agreement_ids = array_unique(array_column($content['project_agreements'],'agreement_id'));
            $temp = $this->agreement->post('/agreement/agreements', ['agreement_ids' => $agreement_ids]);
            $agreements = ($temp['code'] === 0 && $temp['content'])
                ? many_array_column($temp['content'], 'agreement_id') : [];

            $default_result['project_agreements'] = array_map(function ($v) use ($agreements) {
                $v['agreement_code'] = getArraysOfvalue($agreements, $v['agreement_id'], 'agreement_code');
                $v['agreement_name'] = getArraysOfvalue($agreements, $v['agreement_id'], 'agreement_name');
                return $v;
            }, $content['project_agreements']);
        }

        // 文件
        if( $content['project_files'] ){
            $file_ids = array_unique(array_column($content['project_files'],'fileId'));
            $files = $this->fileupload->post('/list',['file_ids' => $file_ids]);
            $files = ($files['code'] === 0 && $files['content'])
                ? many_array_column($files['content'], 'file_id') : [];

            $default_result['project_files'] = array_map(function ($m) use ($files) {
                $file_data = $files[$m['fileId']] ?? [];
                $attributes = $file_data['file_attributes'] ?? '';
                $attributes = json_decode($attributes, true);
                $m['fileName'] = $attributes['name'] ?? '';
                return $m;
            }, $content['project_files']);
        }

        // 住户
        if( $content['project_hoas'] ){
            $tenement_ids = array_unique(array_column($content['project_hoas'],'tenement_id'));
            $tenements = $this->user->post('/tenement/lists',['tenement_ids' => $tenement_ids]);
            $tenements = ($tenements['code'] === 0 && $tenements['content'])
                ? many_array_column($tenements['content'], 'tenement_id') : [];

            $houses = $this->user->post('/house/lists',['tenement_ids' => $tenement_ids]);
            $temp = $houses && $houses['code'] == 0 ? $houses['content'] : [];
            $house_ids = array_unique(array_column($temp,'house_id'));
            $house_data = [];

            if( $house_ids ){
                $house_res =  $this->pm->post('/house/lists',['house_ids'=>$house_ids]);
                $house_temp = $house_res && $house_res['code'] == 0 ? $house_res['content'] : [];
                $house_res = array_column($house_temp,null,'house_id');

                array_map(function ($m) use(&$house_data,$house_res){
                    $arr['space_name'] = getArraysOfvalue($house_res, $m['house_id'], 'space_name');
                    $arr['house_unit'] = getArraysOfvalue($house_res, $m['house_id'], 'house_unit');
                    $arr['house_floor'] = getArraysOfvalue($house_res, $m['house_id'], 'house_floor');
                    $arr['house_room'] = getArraysOfvalue($house_res, $m['house_id'], 'house_room');
                    $house_data[$m['tenement_id']][] = implode("-",$arr);
                },$temp);
            }

            $default_result['project_hoas'] = array_map(function ($m) use($tenements,$house_data){
                if( empty($m['tenement_id']) ){
                    return $m;
                }
                $m['person_name'] = getArraysOfvalue($tenements, $m['tenement_id'], 'real_name');
                $m['person_mobile'] = getArraysOfvalue($tenements, $m['tenement_id'], 'mobile');
                $m['person_email'] = getArraysOfvalue($tenements, $m['tenement_id'], 'email');
                $m['person_address'] = $house_data[$m['tenement_id']] ?? [];
                $m['person_address'] = implode(",",$m['person_address']);
                return $m;
            },$content['project_hoas']);
        }
        rsp_success_json($default_result,'查询成功');
    }
}