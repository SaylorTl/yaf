<?php

use Project\SpaceModel;

class Area extends Base
{

    /**
     * @param array $input
     */
    public function lists($input = [])
    {
        $args = $this->parameters([
            'page' => self::T_INT,
            'pagesize' => self::T_INT,
        ], $input, true);

        $other = $this->parameters([
            'area_name' => self::T_STRING,
            'space_id' => self::T_STRING,
            'tag_id' => self::T_INT,
            'begin_created_at' => self::T_DATE,
            'end_created_at' => self::T_DATE,
        ], $input, false);

        $params = $args + $other;
        $params['project_id'] = $this->project_id;
        $lists = $this->post('adv', '/area/list', $params);
        if (empty($lists)) {
            $this->success([
                'total' => 0,
                'lists' => []
            ]);
        }

        $pmRes = $this->post(
            'pm',
            '/project/projects',
            ['project_ids' => array_column($lists, 'project_id')]
        );

        $userRes = $this->post(
            'user',
            '/employee/lists',
            ['employee_ids' => array_unique(array_merge(array_column($lists, 'create_by'), array_column($lists, 'update_by')))]);

        unset($params['page'], $params['pagesize']);
        $total = $this->post('adv', '/area/count', $params);

        // space
        $space_ids = array_unique(array_filter(array_column($lists, 'space_id')));
        $space_branches = $this->pm->post('/space/batchBranch', ['space_ids' => $space_ids]);
        $space_branches = $space_branches['content'] ?? [];

        $lists = array_map(function ($m) use ($pmRes, $userRes, $space_branches) {
            $m['project_name'] = getArraysOfvalue(array_column($pmRes, null, 'project_id'), $m['project_id'], 'project_name');
            $m['create_by'] = getArraysOfvalue(array_column($userRes, null, 'employee_id'), $m['create_by'], 'full_name');
            $m['update_by'] = getArraysOfvalue(array_column($userRes, null, 'employee_id'), $m['update_by'], 'full_name');
            $branch_info = SpaceModel::parseBranch($space_branches[$m['space_id']] ?? []);
            $m['space_name_full'] = $branch_info['space_name_full'] ?? '';
            return $m;
        }, $lists);

        $result = [
            'total' => $total,
            'lists' => $lists,
        ];

        $this->success($result);
    }


    /**
     * @param array $input
     */
    public function info($input = [])
    {
        $params = $this->parameters([
            'area_id' => self::T_STRING,
        ], $input, true);

        $area_id = $params['area_id'] ?? '';
        $info = $this->post('adv', '/area/info', ['area_id' => $area_id]);
        $pmRes = $this->post('pm', '/project/show', ['project_id' => $info['project_id']]);
        $info['project_name'] = $pmRes['project_name'] ?? '';
        $this->success($info);
    }


    /**
     * @param array $input
     */
    public function create($input = [])
    {
        $args = $this->parameters([
            'project_id' => self::T_STRING,
            'area_name' => self::T_STRING,
            'space_id' => self::T_STRING,
            'area_mode' => self::T_STRING,
            'area_size' => self::T_STRING,
            'tag_id' => self::T_INT,
        ], $input, true);

        $other = $this->parameters([
            'area_remark' => self::T_STRING,
        ], $input, false);

        $resource_id = resource_id_generator(self::RESOURCE_TYPES['adv']);
        $args['area_id'] = $resource_id;
        $args['create_by'] = $this->employee_id;
        $args['update_by'] = $this->employee_id;
        $args = $args + $other;

        $this->post('adv', '/area/create', $args);
        $result = [
            'resource_id' => (string)$resource_id
        ];

        //添加审计日志
        Comm_AuditLogs::push(1325, $resource_id, '添加广告', 1323, $args, '成功');
        $this->success($result);
    }

    /**
     * @param array $input
     */
    public function modify($input = [])
    {
        $args = $this->parameters([
            'area_id' => self::T_STRING,
            'project_id' => self::T_STRING,
            'area_name' => self::T_STRING,
            'space_id' => self::T_STRING,
            'area_mode' => self::T_STRING,
            'area_size' => self::T_STRING,
            'tag_id' => self::T_INT,
        ], $input, true);

        $other = $this->parameters([
            'area_remark' => self::T_STRING,
        ], $input, false);

        $params = $args + $other;
        $params['update_by'] = $this->employee_id;
        $this->post('adv', '/area/modify', $params);
        //添加审计日志
        Comm_AuditLogs::push(
            1325,
            $params['area_id'],
            '更新广告信息',
            1324,
            $params,
             '成功'
        );
        $this->success(true);
    }


}