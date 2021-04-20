<?php

use Project\SpaceModel;

final class Parkplace extends Base
{
    public function lists($params = [])
    {
        if (!isTrueKey($params, 'page', 'pagesize')) rsp_error_tips(10001, "page、pagesize");

        if (isTrueKey($params, 'frame_id')) {
            $tmp = $this->pm->post('/project/projects', ['frame_id' => $params['frame_id']]);
            if (empty($tmp['content'])) rsp_success_json(['total' => 0, 'lists' => []], '暂无数据');
            $project_ids = array_filter(array_unique(array_column($tmp['content'], 'project_id')));
            $params['project_ids'] = isset($params['project_ids']) ? array_merge($params['project_ids'], $project_ids) : $project_ids;
        }

        if (is_not_empty($params, 'project_name')) {
            $tmp = $this->pm->post('/project/projects', ['project_name' => $params['project_name']]);
            if (empty($tmp['content'])) rsp_success_json(['total' => 0, 'lists' => []], '暂无数据');
            $project_ids = array_filter(array_unique(array_column($tmp['content'], 'project_id')));
            $params['project_ids'] = isset($params['project_ids']) ? array_merge($params['project_ids'], $project_ids) : $project_ids;
        }

        if (isTrueKey($params, 'space_id')) {
            $space_ids = $this->pm->post('/space/children', ['space_id' => $params['space_id']]);
            $space_ids = ($space_ids['code'] === 0 && $space_ids['content']) ? $space_ids['content'] : [];
            if (!$space_ids) rsp_success_json(['total' => 0, 'lists' => []]);
            unset($params['space_id']);
            $params['space_ids'] = array_unique(array_filter(array_column($space_ids, 'space_id')));
        }

        $params['project_id'] = $this->project_id;
        $data = $this->pm->post('/parkplace/lists', $params);
        if ($data['code'] !== 0) rsp_error_tips(10002, $data['message']);
        if (empty($data['content'])) rsp_success_json(['total' => 0, 'lists' => []], $data['message']);

        $count = $this->pm->post('/parkplace/count', $params);
        $num = $count['code'] == 0 ? $count['content'] : 0;

        $project_ids = array_filter(array_unique(array_column($data['content'], 'project_id')));
        $tmp = $this->pm->post('/project/lists', ['project_ids' => $project_ids]);
        $projects = $tmp ? many_array_column($tmp['content'], 'project_id') : [];

        $space_ids = array_filter(array_unique(array_column($data['content'], 'space_id')));
        $tmp = $this->pm->post('/space/lists', ['space_ids' => $space_ids]);
        $spaces = $tmp ? many_array_column($tmp['content'], 'space_id') : [];

        // 员工
        $created_by = array_unique(array_filter(array_column($data['content'], 'created_by')));
        $updated_by = array_unique(array_filter(array_column($data['content'], 'updated_by')));
        $employee_ids = array_merge($created_by, $updated_by);
        $employees = $this->user->post('/employee/lists', ['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        $place_owner = array_unique(array_filter(array_column($data['content'], 'place_owner')));
        $tenement = $this->user->post('/tenement/lists', ['tenement_ids' => $place_owner]);
        $tenements = ($tenement['code'] === 0 && $tenement['content']) ? many_array_column($tenement['content'], 'tenement_id') : [];

        // space
        $space_ids = array_unique(array_filter(array_column($data['content'], 'space_id')));
        $space_branches = $this->pm->post('/space/batchBranch', ['space_ids' => $space_ids]);
        $space_branches = $space_branches['content'] ?? [];

        $lists = array_map(function ($m) use ($projects, $spaces, $employees, $tenements, $space_branches) {
            $m['project_name'] = getArraysOfvalue($projects, $m['project_id'], 'project_name');
            $m['space_name'] = getArraysOfvalue($spaces, $m['space_id'], 'space_name');
            $m['place_annex'] = json_decode($m['place_annex'], true);
            $m['status_begin_time'] = $m['status_begin_time'] == 0 ? '' : date('Y-m-d H:i:s', $m['status_begin_time']);
            $m['status_end_time'] = $m['status_end_time'] == 0 ? '' : date('Y-m-d H:i:s', $m['status_end_time']);
            $m['created_by'] = getArraysOfvalue($employees, $m['created_by'], 'full_name');
            $m['updated_by'] = getArraysOfvalue($employees, $m['updated_by'], 'full_name');
            $m['place_owner_name'] = getArraysOfvalue($tenements, $m['place_owner'], 'real_name');
            $m['place_owner_mobile'] = getArraysOfvalue($tenements, $m['place_owner'], 'mobile');
            $branch_info = SpaceModel::parseBranch($space_branches[$m['space_id']] ?? []);
            $m['space_name_full'] = $branch_info['space_name_full'] ?? '';
            return $m;
        }, $data['content']);

        rsp_success_json(['total' => $num, 'lists' => $lists], '查询成功');
    }

    public function add($params = [])
    {
        $info = [];
        $must_field = [
            'space_id' => '所属空间',
            'place_number' => '车位编号',
            'typeof_use' => '车位使用类型'
        ];

        foreach ($must_field as $k => $m) {
            if (!isTruekey($params, $k)) rsp_error_tips(10001, $m);
            $info[$k] = trim($params[$k]);
        }

        $this->checkUsePark($params);

        if ($params['space_id']) {
            $check = ['project_id' => $this->project_id, 'space_id' => $params['space_id']];
            $park_show = $this->pm->post('/parkplace/show', $check);
            if (!empty($park_show['content'])) {
                rsp_die_json(10003,'所属空间数据已存在');
            }
        }

        if (isset($params['place_number'])) {
            $info['place_number'] = trim($params['place_number']);
            if (mb_strlen($info['place_number'], 'UTF8') > 25) rsp_error_tips(10007, '车位编号只能输入25个字符');
            $spaces = $this->pm->post('/space/preeChildren', ['space_id' => $params['space_id']]);
            if ($spaces['content']) {
                $space_ids = array_unique(array_filter(array_column($spaces['content'], 'space_id')));
                $check = ['project_id' => $this->project_id, 'space_ids' => $space_ids, 'full_place_number' => $params['place_number']];
                $park_show = $this->pm->post('/parkplace/show', $check);
                if (!empty($park_show['content'])) {
                    rsp_die_json(10003,'该车位编号已存在');
                }
            }
        } else {
            rsp_error_tips(10001, '车位编号');
        }

        $info = $this->getParams($params, $info);
        $info['created_by'] = $info['updated_by'] = $this->employee_id;
        $place_id = resource_id_generator(self::RESOURCE_TYPES['park_place']);
        if (!$place_id) rsp_error_tips(10001, '资源ID生成失败');
        $info['place_id'] = $place_id;
        $result = $this->pm->post('/parkplace/add', $info);
        if ($result['code'] != 0) rsp_error_tips(10005);
        //添加审计日志
        Comm_AuditLogs::push(1333, $info['place_id'], '添加车位', 1323, $info, '成功');
        rsp_success_json($info['place_id'], '添加成功');
    }

    public function update($params = [])
    {

        $must_field = [
            'place_id' => '车位信息',
            'space_id' => '所属空间',
            'place_number' => '车位编号',
            'typeof_use' => '车位使用类型'
        ];

        foreach ($must_field as $k => $m) {
            if (!isTruekey($params, $k)) rsp_error_tips(10001, $m);
            $info[$k] = trim($params[$k]);
        }

        $this->checkUsePark($params);

        if ($params['space_id']) {
            $check = ['project_id' => $this->project_id, 'space_id' => $params['space_id'], 'not_place_id' => $params['place_id']];
            $park_show = $this->pm->post('/parkplace/show', $check);
            if (!empty($park_show['content'])) {
                rsp_die_json(10003,'所属空间数据已存在');
            }
        }

        if (isset($params['place_number'])) {
            $info['place_number'] = trim($params['place_number']);
            if (mb_strlen($info['place_number'], 'UTF8') > 25) rsp_error_tips(10007, '车位编号只能输入25个字符');
            $spaces = $this->pm->post('/space/preeChildren', ['space_id' => $params['space_id']]);
            if ($spaces['content']) {
                $space_ids = array_unique(array_filter(array_column($spaces['content'], 'space_id')));
                $check = [
                    'project_id' => $this->project_id, 'space_ids' => $space_ids,
                    'full_place_number' => $params['place_number'], 'not_place_id' => $params['place_id']
                ];
                $park_show = $this->pm->post('/parkplace/show', $check);
                if (!empty($park_show['content'])) {
                    rsp_die_json(10003,'该车位编号已存在');
                }
            }
        } else {
            rsp_error_tips(10001, '车位编号');
        }

        $info = $this->getParams($params, $info);
        $info['updated_by'] = $this->employee_id;

        $result = $this->pm->post('/parkplace/update', $info);
        //添加审计日志
        Comm_AuditLogs::push(
            1333,
            $info['place_id'],
            '更新车位',
            1324,
            $info,
            (isset($result['code']) && $result['code'] == 0) ? '成功' : '失败'
        );
        if ($result['code'] != 0) rsp_error_tips(10006);

        rsp_success_json($result['content'], '更新成功');
    }

    private function getParams($params, $info)
    {
        if (isTruekey($params, 'place_type')) {
            $info['place_type'] = $params['place_type'];
        }

        if (isTruekey($params, 'place_status')) {
            $info['place_status'] = $params['place_status'];
        }

        if (isTruekey($params, 'status_begin_time')) {
            $info['status_begin_time'] = strtotime($params['status_begin_time']);
        }

        if (isTruekey($params, 'status_end_time')) {
            $info['status_end_time'] = strtotime($params['status_end_time']);
        }

        if (is_not_empty($params, 'place_area')) {
            $info['place_area'] = $params['place_area'];
        }

        if (isset($params['charging_pile'])) {
            $info['charging_pile'] = $params['charging_pile'];
        }

        if (isset($params['place_owner'])) {
            $info['place_owner'] = $params['place_owner'];
        }

        if (isset($params['property_status'])) {
            $info['property_status'] = $params['property_status'];
        }

        if (isset($params['place_annex'])) {
            if(is_not_json($params['place_annex'])){
                rsp_die_json(10002,'附件参数格式错误');
            }
            $info['place_annex'] = $params['place_annex'];
        }

        if (isset($params['remark'])) {
            $info['remark'] = $params['remark'];
        }

        if (isset($params['typeof_use'])) {
            $info['typeof_use'] = $params['typeof_use'];
        }

        $info['project_id'] = $this->project_id;

        return $info;
    }

    private function checkUsePark($params)
    {
        $stationcfg = $this->pm->post('/project/stationcfg/show', ['project_id' => $this->project_id]);
        if ($stationcfg['code'] != 0) {
            rsp_die_json(10002, '查询项目停车场配置信息失败:' . $stationcfg['message']);
        }

        $fixed_parking_space = $stationcfg['content']['fixed_parking_space'] ?? 0;
        $virtual_parking_space = $stationcfg['content']['virtual_parking_space'] ?? 0;
        if ($fixed_parking_space == 0 && $virtual_parking_space == 0) {
            rsp_die_json(10002, '项目停车场配置中未设置最大固定、虚拟车位数');
        }

        if (isset($params['typeof_use']) && $params['typeof_use'] != '') {
            $park_count = $this->pm->post('/parkplace/count', ['project_id' => $this->project_id, 'typeof_use' => $params['typeof_use']]);
            $park_count = $park_count['code'] == 0 ? $park_count['content'] : 0;
            $park_input = $params['typeof_use'] == 1535 ? $fixed_parking_space : $virtual_parking_space;
            if ($park_count + 1 > $park_input) {
                $typeofuse = $params['typeof_use'] == 1535 ? '固定' : '虚拟';
                rsp_die_json(10002, '不能超过' . $typeofuse . '车位数限制');
            }
        }
    }


}