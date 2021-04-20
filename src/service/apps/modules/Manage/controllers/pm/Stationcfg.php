<?php

class Stationcfg extends Base
{
    protected $fields = [
        'manage_type',
        'platform_type',
        'reform_begin',
        'reform_end',
        'fixed_parking_space',
        'virtual_parking_space',
        'virtual_auto_incr',
        'temp_confirm_enter',
        'arrival_opening_begin_time',
        'arrival_opening_end_time',
        'enter_whitelist',
        'remark',
    ];

    public function show($params = [])
    {
        if (!isTrueKey($params, 'project_id')) {
            rsp_die_json(10001, '缺少参数 project_id');
        }
        $data = $this->pm->post('/project/stationcfg/show', ['project_id' => $params['project_id']]);
        if ($data['code'] != 0) {
            rsp_die_json(10002, '查询项目停车场配置信息失败:' . $data['message']);
        }
        if (empty($data['content'])) {
            rsp_success_json([]);
        }
        $key = ['platform_type', 'fixed_parking_space', 'virtual_parking_space'];
        $data = $this->transformZero($data['content'], $key);
        rsp_success_json($data);
    }

    public function refresh($params = [])
    {
        if (!isTrueKey($params, 'project_id')) rsp_die_json(10001, '缺少参数 project_id');
        $data = ['project_id' => $params['project_id']];
        $data = $this->getParams($data, $params);

        $show = $this->pm->post('/project/stationcfg/show', ['project_id' => $params['project_id']]);
        if ($show['code'] != 0) {
            rsp_die_json(10002, '查询项目停车场配置信息失败:' . $show['message']);
        }

        $method = empty($show['content']) ? 'add' : 'update';

        if($method == 'update' && (isset($params['fixed_parking_space']) || isset($params['virtual_parking_space']))){
            $fixed_parking_input = $params['fixed_parking_space'] ?? 0;
            $virtual_parking_input = $params['virtual_parking_space'] ?? 0;

            $fixed_park_count = $this->pm->post('/parkplace/count', ['project_id' => $params['project_id'], 'typeof_use' => 1535]);
            $fixed_park_count = $fixed_park_count['code'] == 0 ? $fixed_park_count['content'] : 0;

            $virtual_park_count = $this->pm->post('/parkplace/count', ['project_id' => $params['project_id'], 'typeof_use' => 1536]);
            $virtual_park_count = $virtual_park_count['code'] == 0 ? $virtual_park_count['content'] : 0;

            if($fixed_parking_input < $fixed_park_count) rsp_die_json(10003,'固定车位数不能小于已有车位数');
            if($virtual_parking_input < $virtual_park_count) rsp_die_json(10003,'虚拟车位数不能小于已有车位数');
        }

        $result = $this->pm->post('/project/stationcfg/' . $method, $data);
        if ($result['code'] != 0) rsp_die_json(10003, $result['message']);

        rsp_success_json();
    }


    private function getParams($data, $params)
    {
        foreach ($this->fields as $val) {
            if (is_not_empty($params, $val)) {
                $data[$val] = $params[$val];
            }
        }
        if (isset($data['reform_begin'])) {
            if (!validate_date($data['reform_begin'], 'Y-m-d')) {
                rsp_die_json(10003, '改造年月参数格式错误');
            }
        }
        if (isset($data['reform_end'])) {
            if (!validate_date($data['reform_end'], 'Y-m-d')) {
                rsp_die_json(10003, '改造年月参数格式错误');
            }
        }
        if (isset($data['virtual_auto_incr'])) {
            if (!in_array($data['virtual_auto_incr'], ['Y', 'N'])) {
                rsp_die_json(10003, '虚拟车位数自增参数格式错误');
            }
        }
        if (isset($data['temp_confirm_enter'])) {
            if (!in_array($data['temp_confirm_enter'], ['Y', 'N'])) {
                rsp_die_json(10003, '临停确认进场参数格式错误');
            }
        }
        if (isset($data['enter_whitelist'])) {
            if (!in_array($data['enter_whitelist'], ['Y', 'N'])) {
                rsp_die_json(10003, '进场云端白名单参数格式错误');
            }
        }
        return $data;
    }

    private function transformZero($data, $key)
    {
        if (!$data) return $data;
        foreach ($key as $k) {
            if (isset($data[$k]) && $data[$k] == 0) {
                $data[$k] = '';
            }
        }
        return $data;
    }
}