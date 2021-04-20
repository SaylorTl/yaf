<?php

use Project\SpaceModel;

class Arrears extends Base
{
    public function lists($params = [])
    {
        log_message(__METHOD__ . '------' . json_encode($params));
        if (!isTrueKey($params, 'page', 'pagesize')) rsp_die_json(10001, 'page pagesize 参数缺失或错误');
        if (isTrueKey($params, 'space_id')) {
            $houses = $this->pm->post('/house/basic/lists', ['space_id' => $params['space_id']]);
            $houses = $houses['content'] ?? [];
            if (!$houses) rsp_success_json(['total' => 0, 'lists' => []]);
            unset($params['space_id']);
            $params['house_id'] = $houses[0]['house_id'];
        }
        $params['project_id'] = $this->project_id;

        $lists = $this->pm->post('/arrears/lists', $params);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        $total = $this->pm->post('/arrears/count', $params);
        if ($total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => count($lists['content']), 'lists' => $lists['content']]);

        // houses
        $houses = $this->pm->post('/house/basic/lists', ['house_ids' => array_unique(array_filter(array_column($lists['content'],'house_id')))]);
        $houses = ($houses['code'] === 0 && $houses['content']) ? many_array_column($houses['content'], 'house_id') : [];

        // space
        $space_ids = array_unique(array_filter(array_column($houses, 'space_id')));
        $space_branches = $this->pm->post('/space/batchBranch', ['space_ids' => $space_ids]);
        $space_branches = $space_branches['content'] ?? [];

        $result = array_map(function ($m) use ($houses, $space_branches) {
            $m['space_id'] = getArraysOfvalue($houses, $m['house_id'], 'space_id');
            $branch_info = SpaceModel::parseBranch($space_branches[$m['space_id']] ?? []);
            $m['space_name_full'] = $branch_info['space_name_full'] ?? '';
            return $m;
        }, $lists['content']);
        rsp_success_json(['total' => (int)$total['content'], 'lists' => $result]);
    }

    public function total($params = [])
    {
        log_message(__METHOD__ . '------' . json_encode($params));
        if (isTrueKey($params, 'space_id')) {
            $houses = $this->pm->post('/house/basic/lists', ['space_id' => $params['space_id']]);
            $houses = $houses['content'] ?? [];
            if (!$houses) rsp_success_json(['info' => ['total_amount_sum' => 0.00, 'penalty_money_sum' => 0.00]]);
            unset($params['space_id']);
            $params['house_id'] = $houses[0]['house_id'];
        }
        $params['project_id'] = $this->project_id;
        $total = $this->pm->post('/arrears/sum', $params);
        if ($total['code'] !== 0 || !$total['content']) rsp_success_json(['info' => []]);
        rsp_success_json(['info' => $total['content']]);
    }
}