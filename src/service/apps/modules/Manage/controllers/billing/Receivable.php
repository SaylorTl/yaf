<?php

use  Project\SpaceModel;
use Rule\RuleModel;
use Rule\PenaltyModel;
use Project\ArrearsModel;

class Receivable extends Base
{

    protected $redis;

    public function __construct()
    {
        parent::__construct();
        $this->redis = Comm_Redis::getInstance();
        $this->redis->select(8);
    }


    public function lists($params = [])
    {
        log_message(__METHOD__ . '-------' . json_encode($params));
        if (isTrueKey($params, 'page', 'pagesize') == false) {
            rsp_die_json(10001, '参数缺失');
        }
        if (isTrueKey($params, 'space_id')) {
            $space_ids = $this->pm->post('/space/children', ['space_id' => $params['space_id']]);
            $space_ids = ($space_ids['code'] === 0 && $space_ids['content']) ? $space_ids['content'] : [];
            if (!$space_ids) rsp_success_json(['total' => 0, 'lists' => []]);
            unset($params['space_id']);
            $params['space_ids'] = array_unique(array_filter(array_column($space_ids, 'space_id')));
        }
        $params['project_id'] = $this->project_id;
        $receivable_lists = $this->billing->post('/receivableBill/lists', $params);
        if (0 != $receivable_lists['code']) {
            rsp_die_json(10002, $receivable_lists['message']);
        }

        if (!$receivable_lists['content']) {
            rsp_success_json(['total' => 0, 'lists' => []]);
        }
        $count = $this->billing->post('/receivableBill/count', $params);
        if (0 != $count['code']) {
            rsp_die_json(10002, $count['message']);
        }

        $space_ids = array_unique(array_column($receivable_lists['content'], 'space_id'));
        $space_branches = $this->pm->post('/space/batchBranch', ['space_ids' => $space_ids]);
        $space_branches = $space_branches['content'] ?? [];

        $employee_ids = array_unique(array_column($receivable_lists['content'], 'updated_by'));
        $employees = $this->user->post('/employee/lists', ['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        $data = array_map(function ($m) use ($space_branches, $employees) {
            $branch_info = SpaceModel::parseBranch($space_branches[$m['space_id']] ?? []);
            $m['space_name_full'] = $branch_info['space_name_full'] ?? '';
            if ($m['space_name_full']) {
                $m['space_name_full'] = str_replace('-', '/', $m['space_name_full']);
            }
            $m['updated_by'] = getArraysOfvalue($employees, $m['updated_by'], 'full_name');
            return $m;
        }, $receivable_lists['content']);

        rsp_success_json(['total' => $count['content']['total'], 'lists' => $data]);
    }


    public function exceptionCancel($params = [])
    {
        log_message(__METHOD__ . '-------' . json_encode($params));
        if (isTrueKey($params, 'receivable_bill_ids') == false) {
            rsp_die_json(10001, '参数缺失');
        }
        if (!isset($params['remark'])) {
            rsp_die_json(10001, '参数缺失2');
        }
        if (!is_array($params['receivable_bill_ids'])) {
            rsp_die_json(10001, 'receivable_bill_ids格式错误');
        }
        $result = $this->billing->post('/receivableBill/lists', [
            'receivable_bill_ids' => $params['receivable_bill_ids']
        ]);
        if (empty($result['content'])) {
            rsp_die_json(10001, '数据查询失败');
        }
        $space_id = $result['content'][0]['space_id'];
        $result = $this->billing->post('/receivableBill/update', [
            'receivable_bill_ids' => $params['receivable_bill_ids'],
            'remark' => $params['remark'],
            'billing_status_tag_id' => 1507,
            'updated_by' => $this->employee_id
        ]);
        if (0 != $result['code']) {
            rsp_die_json(10002, '异常消单失败');
        }
        //修改欠费记录
        ArrearsModel::handle(['space_id' => $space_id]);
        rsp_success_json(1);
    }


    public function add($params = [])
    {
        log_message(__METHOD__ . '-------' . json_encode($params));
        $must_params = [
            'space_id',
            'billing_property_name',
            'billing_property_phone',
            "billing_data",
        ];
        if ($miss_params = get_empty_fields($must_params, $params)) {
            rsp_die_json(10001, '参数缺失');
        }
        if (!is_array($params['billing_data'])) {
            rsp_die_json(10001, '账单数据格式有误');
        }
        $house_show = $this->pm->post('/house/show', ['project_id' => $this->project_id, 'space_id' => $params['space_id']]);
        if (!$house_show || 0 != $house_show['code']) {
            rsp_die_json(10002, '房产信息查询失败');
        }
        if (!$house_show['content']) {
            rsp_die_json(10002, '房产信息不存在');
        }

        $add_params = [];
        foreach ($params['billing_data'] as $k => $billing_data) {
            if (!isTrueKey($billing_data, 'billing_account_id', 'billing_start_time', 'billing_end_time', 'billing_calculate_num')) {
                rsp_die_json(10002, '账单数据有误');
            }

            if (!isset($billing_data['billing_penalty_day'])) {
                rsp_die_json(10002, '账单数据有误');
            }

            if (!isset($billing_data['penalty_start_time']) || !isset($billing_data['penalty_end_time'])) {
                rsp_die_json(10002, '违约时间范围有误');
            }

            $tmp = $this->billing->post('/billingAccount/lists', [
                'billing_account_id' => $billing_data['billing_account_id']
            ]);
            if (0 != $tmp['code']) {
                rsp_die_json(10002, $tmp['message']);
            }
            if (!$tmp['content']) {
                rsp_die_json(10002, '科目信息不存在');
            }
            $billing_account_show = $tmp['content'][0];

            $business_config_show = $this->billing->post('/businessConfig/businessConfigShow', [
                'project_id' => $this->project_id,
                'space_id' => $params['space_id'],
                'billing_account_id' => $billing_account_show['billing_account_id'],
                'status_tag_id' => 1381
            ]);
            if (0 != $business_config_show['code']) {
                rsp_die_json(10002, $business_config_show['message']);
            }

            $billing_type = '';
            if ($business_config_show['content']['billing_type_name'] == '物业计费') {
                $billing_type = 'propertyFee';
            } elseif ($business_config_show['content']['billing_type_name'] == '停车计费') {
                $billing_type = 'Parking';
            } else {
                rsp_die_json(10002, '暂不支持该类型【' . $business_config_show['content']['billing_type_name'] . '】计费');
            }

            $object = new RuleModel();
            $result = $object->setParams([
                'rule_id' => $business_config_show['content']['rule_id'],
                'billing_account_name' => $billing_account_show['billing_account_name'],
                'type' => $billing_type,
                'project_id' => $this->project_id,
                'business_params' => [
                    'billing_calculate_num' => $billing_data['billing_calculate_num']
                ]
            ])->cost();
            if (0 != $result['code']) {
                rsp_die_json(10002, $billing_account_show['billing_account_name'] . ':' . $result['msg']);
            }

            $add_params[$k] = [
                'updated_by' => $this->employee_id ?? '',
                'project_id' => $this->project_id,
                'space_id' => $params['space_id'],
                'billing_account_id' => $billing_account_show['billing_account_id'],
                'billing_status_tag_id' => 1506,
                'billing_source_tag_id' => 1508,
                'billing_penalty_amount' => 0,
                'billing_penalty_base' => 0,
                'billing_penalty_cycle' => '',
                'billing_penalty_day' => $billing_data['billing_penalty_day'],
                'billing_amount' => bcmul($result['content']['total_fee'], 100),
                'billing_base' => isset($result['content']['price']) ? bcmul($result['content']['price'], 100) : 0,
                'billing_property_name' => $params['billing_property_name'],
                'billing_property_phone' => $params['billing_property_phone'],
                'billing_calculate_num' => bcmul($billing_data['billing_calculate_num'], 100),
                'billing_start_time' => strtotime($billing_data['billing_start_time']),
                'billing_end_time' => strtotime($billing_data['billing_end_time']),
                'bill_base_unit' => $result['content']['unit'] ?? ''
            ];
            //计算违约金
            if ($billing_account_show['billing_penalty_tag_id'] == '1420') {

                $penalty_result = $object->setParams([
                    'rule_id' => $billing_account_show['billing_penalty_rule_id'],
                    'billing_account_name' => $billing_account_show['billing_account_name'],
                    'type' => 'penaltyFee',
                    'project_id' => $this->project_id,
                    'business_params' => [
                        'total_days' => $billing_data['billing_penalty_day'],
                        'amount' => $result['content']['total_fee']
                    ]
                ])->cost();
                if (0 != $penalty_result['code']) {
                    rsp_die_json(10002, $billing_account_show['billing_account_name'] . ':' . $penalty_result['msg']);
                }
                //查询违约周期标签
                $res = $this->tag->post('/tag/show', ['tag_id' => $billing_account_show['billing_penalty_cycle_tag_id']]);
                $tag_info = $res['code'] == 0 ? $res['content'] : [];
                $add_params[$k]['billing_penalty_cycle'] = $tag_info ? $billing_account_show['billing_penalty_cycle'] . $tag_info['tag_name'] : '';
                $add_params[$k]['billing_penalty_base'] = $penalty_result['content']['price'];
                $add_params[$k]['billing_penalty_amount'] = bcmul($penalty_result['content']['total_fee'], 100);
                //违约开始时间与结束时间
                $add_params[$k]['penalty_start_time'] = strtotime($billing_data['penalty_start_time']) ?: 0;
                $add_params[$k]['penalty_end_time'] = strtotime($billing_data['penalty_end_time']) ?: 0;
            }
            $receivable_bill_id = resource_id_generator(self::RESOURCE_TYPES['receivable_bill']);
            if (!$receivable_bill_id) {
                rsp_die_json(10005, '资源ID创建失败');
            }
            $add_params[$k]['receivable_bill_id'] = $receivable_bill_id;
        }
        $result = $this->billing->post('/receivableBill/bulkAdd', $add_params);
        if (0 != $result['code']) {
            rsp_die_json(10002, $result['message']);
        }

        rsp_success_json(1, 'success');
    }

    public function charge($params = [])
    {
        if (isTrueKey($params, 'receivable_bill_ids') === false) {
            rsp_die_json(10001, '参数缺失');
        }

        if (!is_array($params['receivable_bill_ids'])) {
            rsp_die_json(10001, '参数格式错误');
        }

        $uuid = strtoupper(uuid('charge', ''));


        $receivable_lists = $this->billing->post('/receivableBill/lists', [
            'receivable_bill_ids' => $params['receivable_bill_ids'],
            'billing_status_tag_id' => 1506,
        ]);

        if (!$receivable_lists || 0 !== (int)$receivable_lists['code']) {
            rsp_die_json(10002, '账单信息查询异常');
        }

        if (!$receivable_lists['content']) {
            rsp_die_json(10002, '账单信息不存在');
        }

        $space_ids = array_unique(array_column($receivable_lists['content'], 'space_id'));
        if (count($space_ids) > 1) {
            rsp_die_json(10002, '计费存在其他空间的账单信息');
        }

        $house_show = $this->pm->post('/house/show', ['space_id' => implode(',', $space_ids)]);
        if (!$house_show || 0 !== (int)$house_show['code']) {
            rsp_die_json(10002, '房产信息查询异常');
        }
        if (!$house_show['content']) {
            rsp_die_json(10002, '房产信息不存在');
        }

        $amount = $total_amount = 0;
        $details = [];
        foreach ($receivable_lists['content'] as $v) {

            $amount = bcadd($amount, $v['billing_amount'], 2);
            $total_amount = bcadd($total_amount, $v['billing_amount'], 2);
            $total_amount = bcadd($total_amount, $v['billing_penalty_amount'], 2);
            $sub_total_amount = $sub_amount = bcadd($v['billing_amount'], $v['billing_penalty_amount'], 2);
            if ($house_show['content']['house_collect_penalty'] !== 'Y') {//不收违约金
                $sub_amount = $v['billing_amount'];
            }
            $details[] = [
                'receivable_bill_id' => $v['receivable_bill_id'],
                'sub_total_amount' => $sub_total_amount,
                'sub_amount' => $sub_amount,
                'billing_account_name' => $v['billing_account_name'],
                'billing_account_category' => 698
            ];
        }

        if ($house_show['content']['house_collect_penalty'] === 'Y') {//收违约金
            $amount = $total_amount;
        }

        $return = [
            'charge_uuid' => $uuid,
            'total_amount' => $total_amount,
            'amount' => $amount,
            'details' => $details,
            'attach' => ['receivable_bill_ids' => array_column($details, 'receivable_bill_id')]
        ];
        $set_redis_data = ['total_pay_amount' => bcmul($amount, 100), 'detail' => [698 => ['amount' => $amount]]];
        $chargeUuid = json_encode($set_redis_data, JSON_UNESCAPED_UNICODE);
        $this->redis->setex(OrderModel::PAY_AMOUNT_REDIS_KEY . $uuid, 360, $chargeUuid);

        rsp_success_json($return, 'success');
    }


    public function getPenaltyTime($params = [])
    {
        if (isTrueKey($params, 'billing_account_id') === false) {
            rsp_die_json(10001, '计费科目缺失');
        }
        if (isTrueKey($params, 'billing_end_time') === false) {
            rsp_die_json(10001, '计费结束时间缺失');
        }

        if (!isset($params['billing_penalty_day'])) {
            rsp_die_json(10001, '违约次数缺失');
        }

        if (0 == $params['billing_penalty_day']) {
            rsp_success_json([
                "penalty_start_time" => 0,
                'penalty_end_time' => 0,
            ], 'success');
        }

        $tmp = $this->billing->post('/billingAccount/lists', [
            'billing_account_id' => $params['billing_account_id']
        ]);

        if (0 != $tmp['code']) {
            rsp_die_json(10002, $tmp['message']);
        }
        if (!$tmp['content']) {
            rsp_die_json(10002, '科目信息不存在');
        }
        $billing_account_show = $tmp['content'][0];

        $penalty_result = PenaltyModel::getPenaltyTimeFrame($billing_account_show, $params);
        if (!$penalty_result) {
            rsp_die_json(10002, '科目周期有误');
        }

        rsp_success_json($penalty_result, 'success');
    }

    public function tempUpdate($params = [])
    {
        if (isTrueKey($params, 'receivable_bill_id', 'create_time') === false) {
            rsp_die_json(10001, '参数缺失');
        }

        $result = $this->billing->post('/temp/update', [
            'receivable_bill_id' => $params['receivable_bill_id'],
            'create_time' => $params['create_time'],
        ]);

        if (0 !== $result['code']) {
            rsp_die_json(10002, $result['message']);
        }
        rsp_success_json(1);
    }

}