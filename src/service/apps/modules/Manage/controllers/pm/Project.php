<?php

use Project\ConstantModel as Constant;

final class Project extends Base
{
    public function lists($params = [])
    {
        if (!isTrueKey($params, 'page', 'pagesize')) rsp_die_json(10001, 'page pagesize 参数缺失或错误');

        $project_id = $params['project_id'] ?? '';
        if ($project_id) unset($params['project_ids']);
        $params['project_id'] = $project_id ?: $this->project_id;

        if($_SESSION['member_p_role_id'] == 0 && isset($params["project_ids"]) && $params["project_ids"] === 'all') {
            unset($params['project_id']);
        }

        // 查询oauth2.0 租户关联第三方APPID信息
        if (isTrueKey($params, 'app_name')) {
            $result = (new Comm_Gateway())->gateway(
                ['name_en' => $params['app_name']],
                'admin.appbinding.redisShow',
                ['service' => 'auth2']
            );
            if ($result['code'] != 0 || empty($result['content'])) {
                rsp_die_json(10007, '查询失败');
            }
            $app_type = $result['content']['app_type'] ?? '';
            $app_id = $result['content']['jsfrom_source_id'] ?? '';
            $app_type == 'client' ? $params['client_app_id'] = $app_id : $params['admin_app_id'] = $app_id;
        }

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
        $employees = $this->user->post('/employee/lists', ['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        // oauth2.0 租户、第三方APPID绑定信息
        $admin_app_ids = array_unique(array_filter(array_column($lists['content'], 'admin_app_id')));
        $client_app_ids = array_unique(array_filter(array_column($lists['content'], 'client_app_id')));
        $third_binding_ids = array_unique(array_merge($admin_app_ids, $client_app_ids));
        $bindingInfo = (new Comm_Gateway())->gateway(
            ['jsfrom_source_ids' => $third_binding_ids, 'not_limit_page' => 'Y'],
            'admin.appbinding.data',
            ['service' => 'auth2']
        );
        $bindingInfo = $bindingInfo['content'] ?? [];
        $zuhuInfo = [];
        if (!empty($bindingInfo)) {
            array_map(function ($m) use (&$zuhuInfo) {
                $m['app_remark'] = $m['name_zh'] . '-' . $m['third_app_id'];
                $zuhuInfo[$m['jsfrom_source_id']][] = $m;
            }, $bindingInfo);
        }

        // resource lites
        $resource_lites = $this->resource->post('/resource/id/lite', ['resource_ids' => array_unique(array_filter(array_column($lists['content'], 'project_id')))]);
        $resource_lites = ($resource_lites['code'] === 0 && $resource_lites['content']) ? many_array_column($resource_lites['content'], 'resource_id') : [];


        rsp_success_json(['total' => (int)$total['content'], 'lists' => array_map(function ($m) use ($frames, $companies, $employees, $zuhuInfo, $resource_lites) {
            $m['frame_name'] = getArraysOfvalue($frames, $m['frame_id'], 'frame_name');
            $m['property_company_name'] = getArraysOfvalue($companies, $m['property_company_id'], 'company_name');
            $m['developer_company_name'] = getArraysOfvalue($companies, $m['developer_company_id'], 'company_name');
            $m['created_by'] = getArraysOfvalue($employees, $m['created_by'], 'full_name');
            $m['updated_by'] = getArraysOfvalue($employees, $m['updated_by'], 'full_name');
            $m['charge_person'] = getArraysOfvalue($employees, $m['charge_employee_id'], 'full_name');
            $m['invoice_tag_id'] = $m['invoice_tag_id'] ?: '';
            $m['admin_app_info'] = $zuhuInfo[$m['admin_app_id']] ?? [];
            $m['client_app_info'] = $zuhuInfo[$m['client_app_id']] ?? [];
            $m['resource_lite'] = getArraysOfvalue($resource_lites, $m['project_id'], 'resource_lite');
            $m['manage_type_tag_id'] = $m['manage_type_tag_id'] ?: '';
            $m['charge_type_tag_id'] = $m['charge_type_tag_id'] ?: '';
            $m['service_type_tag_id'] = $m['service_type_tag_id'] ?: '';
            $m['guard_type_tag_id'] = $m['guard_type_tag_id'] ?: '';
            $m['cleaning_type_tag_id'] = $m['cleaning_type_tag_id'] ?: '';
            $m['has_canteen_tag_id'] = $m['has_canteen_tag_id'] ?: '';
            $m['has_dorm_tag_id'] = $m['has_dorm_tag_id'] ?: '';
            return $m;
        }, $lists['content'])]);
    }

    public function projects($params = [])
    {
        $params['project_id'] = $this->project_id;
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

        // oauth2.0 租户、第三方APPID绑定信息
        $third_binding_ids = array_unique([$show['content']['admin_app_id'], $show['content']['client_app_id']]);
        $bindingInfo = (new Comm_Gateway())->gateway(
            ['oauth_third_app_ids' => $third_binding_ids, 'not_limit_page' => 'Y'],
            'admin.appbinding.data',
            ['service' => 'auth2']
        );
        $bindingInfo = $bindingInfo['content'] ? many_array_column($bindingInfo['content'], 'oauth_third_app_id') : [];
        $show['content']['admin_app_name'] = getArraysOfvalue($bindingInfo, $show['content']['admin_app_id'], 'name_en');
        $show['content']['client_app_name'] = getArraysOfvalue($bindingInfo, $show['content']['client_app_id'], 'name_en');

        rsp_success_json($show['content']);
    }

    public function add($params = [])
    {
        $fields = [
            'project_name', 'property_company_id','frame_id', 'status_tag_id',
             'household_num_tag_id','country_id', 'province_id', 'city_id', 'region_id', 'address_detail', 'location',
            'project_types', 'project_files'
        ];
        if ($diff_fields = get_empty_fields($fields, $params)) rsp_die_json(10001, '缺少参数 ' . implode(' ', $diff_fields));
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
        foreach (json_decode($params['project_types'] ?? '', true) ?: [] as $item) {
            if (!isTrueKey($item, 'type_id')) rsp_die_json(10001, '项目类型不能为空');
        }

        $params['project_agreements'] = json_encode(array_filter(array_map(function ($m) {
            return get_empty_fields(['agreement_id'], $m) === ['agreement_id'] ? null : $m;
        }, json_decode($params['project_agreements'] ?? '', true) ?: [])));

        $checkHoas = Constant::HOAS;
        $params['project_hoas'] = json_encode(array_filter(array_map(function ($m) use ($checkHoas) {
            return get_empty_fields($checkHoas, $m) === $checkHoas ? null : $m;
        }, json_decode($params['project_hoas'] ?? '', true) ?: [])));

        $params['project_phases'] = json_encode(array_filter(array_map(function ($m) {
            return get_empty_fields(['phase_name'], $m) === ['phase_name'] ? null : $m;
        }, json_decode($params['project_phases'] ?? '', true) ?: [])));

        $checkFiles = Constant::FILES;
        $params['project_files'] = json_encode(array_filter(array_map(function ($m) use ($checkFiles) {
            return get_empty_fields($checkFiles, $m) === $checkFiles ? null : $m;
        }, json_decode($params['project_files'] ?? '', true) ?: [])));

        $project_id = $params['project_id'] = resource_id_generator(self::RESOURCE_TYPES['project']);
        if (!$params['project_id']) rsp_die_json(10001, '生成资源ID失败');

        // todo 添加到用户微服务


        $params['created_at'] = $params['updated_at'] = time();
        $params['created_by'] = $params['updated_by'] = $this->employee_id;

        unset($params['resource_lite']);
        $result = $this->pm->post('/project/add', $params);
        if ($result['code'] !== 0) rsp_die_json(10001, $result['message']);
        //添加审计日志
        Comm_AuditLogs::push(1335, $result['content'], '添加项目', 1323, $params, '成功');
        rsp_success_json($result['content']);
    }

    public function update($params = [])
    {
        $fields = [
            'project_id', 'project_name', 'property_company_id', 'frame_id', 'status_tag_id','household_num_tag_id',
            'country_id', 'province_id', 'city_id', 'region_id', 'address_detail', 'location',
            'project_types'
        ];
        if ($diff_fields = get_empty_fields($fields, $params)) rsp_die_json(10001, '缺少参数 ' . implode(' ', $diff_fields));
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
        foreach (json_decode($params['project_types'] ?? '', true) ?: [] as $item) {
            if (!isTrueKey($item, 'type_id')) rsp_die_json(10001, '项目类型不能为空');
        }

        $params['project_agreements'] = json_encode(array_filter(array_map(function ($m) {
            return get_empty_fields(['agreement_id'], $m) === ['agreement_id'] ? null : $m;
        }, json_decode($params['project_agreements'] ?? '', true) ?: [])));

        $checkHoas = Constant::HOAS;
        $params['project_hoas'] = json_encode(array_filter(array_map(function ($m) use ($checkHoas) {
            return get_empty_fields($checkHoas, $m) === $checkHoas ? null : $m;
        }, json_decode($params['project_hoas'] ?? '', true) ?: [])));

        $params['project_phases'] = json_encode(array_filter(array_map(function ($m) {
            return get_empty_fields(['phase_name'], $m) === ['phase_name'] ? null : $m;
        }, json_decode($params['project_phases'] ?? '', true) ?: [])));

        $checkFiles = Constant::FILES;
        $project_files = is_array($params['project_files'])
            ? $params['project_files'] : json_decode($params['project_files'], true);
        $params['project_files'] = json_encode(array_filter(array_map(function ($m) use ($checkFiles) {
            return get_empty_fields($checkFiles, $m) === $checkFiles ? null : $m;
        }, $project_files)));

        $params['updated_at'] = time();
        $params['updated_by'] = $this->employee_id;
        $params['project_id'] = $this->project_id;
        //同步修改房子违约金状态
        $this->_collect_penalty($params['project_id'], $params['project_collect_penalty']);

        unset($params['resource_lite']);
        $result = $this->pm->post('/project/update', $params);
        //添加审计日志
        Comm_AuditLogs::push(
            1335,
            $params['project_id'],
            '更新项目信息',
            1324,
            $params,
            (!isset($result['code']) || $result['code'] != 0) ? '失败' : '成功'
        );
        if ($result['code'] !== 0) rsp_die_json(10001, $result['message']);

        rsp_success_json($result['content']);
    }

    public function relevanceDetails($params = [])
    {
        if (false == isTrueKey($params, 'project_id')) {
            rsp_die_json(10001, '缺少项目编号信息');
        }

        $default_result = [
            'project_agreements' => [],
            'project_files' => [],
            'project_hoas' => []
        ];

        $params['page'] = 1;
        $params['pagesize'] = 1;
        $params['project_id'] = $this->project_id;
        $lists = $this->pm->post('/project/lists', $params);
        $content = $lists['content'][0] ?? [];
        if ($lists['code'] !== 0 || empty($content)) {
            rsp_success_json($default_result, '查询成功');
        }

        // 合同
        if ($content['project_agreements']) {
            $agreement_ids = array_unique(array_column($content['project_agreements'], 'agreement_id'));
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
        if ($content['project_files']) {
            $file_ids = array_unique(array_column($content['project_files'], 'fileId'));
            $files = $this->fileupload->post('/list', ['file_ids' => $file_ids]);
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
        if ($content['project_hoas']) {
            $tenement_ids = array_unique(array_column($content['project_hoas'], 'tenement_id'));
            $tenements = $this->user->post('/tenement/lists', ['tenement_ids' => $tenement_ids]);
            $tenements = ($tenements['code'] === 0 && $tenements['content'])
                ? many_array_column($tenements['content'], 'tenement_id') : [];

            $houses = $this->user->post('/house/lists', ['tenement_ids' => $tenement_ids]);
            $temp = $houses && $houses['code'] == 0 ? $houses['content'] : [];
            $house_ids = array_unique(array_column($temp, 'house_id'));
            $house_data = [];

            if ($house_ids) {
                $house_res = $this->pm->post('/house/lists', ['house_ids' => $house_ids]);
                $house_temp = $house_res && $house_res['code'] == 0 ? $house_res['content'] : [];
                $house_res = array_column($house_temp, null, 'house_id');

                array_map(function ($m) use (&$house_data, $house_res) {
                    $arr['space_name'] = getArraysOfvalue($house_res, $m['house_id'], 'space_name');
                    $arr['house_unit'] = getArraysOfvalue($house_res, $m['house_id'], 'house_unit');
                    $arr['house_floor'] = getArraysOfvalue($house_res, $m['house_id'], 'house_floor');
                    $arr['house_room'] = getArraysOfvalue($house_res, $m['house_id'], 'house_room');
                    $house_data[$m['tenement_id']][] = implode("-", $arr);
                }, $temp);
            }

            $default_result['project_hoas'] = array_map(function ($m) use ($tenements, $house_data) {
                if (empty($m['tenement_id'])) {
                    return $m;
                }
                $m['person_name'] = getArraysOfvalue($tenements, $m['tenement_id'], 'real_name');
                $m['person_mobile'] = getArraysOfvalue($tenements, $m['tenement_id'], 'mobile');
                $m['person_email'] = getArraysOfvalue($tenements, $m['tenement_id'], 'email');
                $m['person_address'] = $house_data[$m['tenement_id']] ?? [];
                $m['person_address'] = implode(",", $m['person_address']);
                return $m;
            }, $content['project_hoas']);
        }
        rsp_success_json($default_result, '查询成功');
    }

    public function _collect_penalty($project_id, $project_collect_penalty)
    {
        try {
            $project_show = $this->pm->post('/project/show', ['project_id' => $project_id]);
            if ($project_show['code'] != 0 || empty($project_show['content'])) {
                throw new \Exception('项目详情失败，原因：' . $project_show['message']);
            }
            //项目原违约金状态不一样,则需要修改同步房子违约金状态
            if ($project_collect_penalty != $project_show['content']['project_collect_penalty']) {
                $result = $this->pm->post('/house/change/penalty', [
                    'project_id' => $project_id,
                    'house_collect_penalty' => $project_collect_penalty
                ]);
                if ($result['code'] != 0) {
                    throw new \Exception('该项目房产违约金状态修改失败');
                }
            }
        } catch (\Exception $e) {
            log_message('----房产是否收取违约金根据项目动态控制修改失败--【' . $e->getMessage() . '】');
        }
    }

}