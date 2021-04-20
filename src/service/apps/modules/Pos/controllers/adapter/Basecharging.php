<?php

class Basecharging extends Base
{
    /**
     * 计费类型 key为调用计费类型
     * 0 所有 1 物业费 2 水费 3 维修基金费用 4 停车费
     * type 订单类型tag值 697：停车场 698：物业费
     */
    const CHARGE_TYPES_METHODS = [
        1 => ['type' => 698, 'name' => '物业费', 'method' => 'property_management_fee'],
        4 => ['type' => 697, 'name' => '停车费', 'method' => 'station_fee'],
    ];

    /**
     * @var
     * redis 对象
     */
    protected $redis;

    public function __construct()
    {
        parent::__construct();
        $this->redis = Comm_Redis::getInstance();
        $this->redis->select(8);
    }

    /**
     * @param $params
     * @return array
     * 计费列表
     * data_type 指定查询计费类型 0 所有 1 物业费 2 水费 3 维修基金费用 4 停车费
     */
    public function getChargeLists($params)
    {
        $fees = [];
        $total_pay_amount = 0;
        $dataType = $params['data_type'] ?? 0;
        $chargeTypes = self::CHARGE_TYPES_METHODS[$dataType] ?? [];
        $chargeTypes = empty($chargeTypes) ? self::CHARGE_TYPES_METHODS : [$chargeTypes];

        // 检测UUID信息
        $uuidInfo = [];
        if (isTrueKey($params, 'charge_uuid')) {
            $chargeUuid = $this->redis->get(OrderModel::PAY_AMOUNT_REDIS_KEY . $params['charge_uuid']);
            $uuidInfo = !empty($chargeUuid) ? json_decode($chargeUuid, true) : [];
        } else {
            $params['charge_uuid'] = strtoupper(uuid('charge', ''));
        }

        foreach ($chargeTypes as $k => $v) {
            $arr['charge_name'] = $v['name'];
            $method = $v['method'];
            $charge_info = $this->$method($params);

            $uuidInfo['detail'][$v['type']] = $v;
            $payAmount = (int)bcmul($charge_info['pay_amount'], 100);
            $couponAmount = (int)bcmul($charge_info['coupon_amount'], 100);
            $realAmount = $payAmount - $couponAmount;
            $uuidInfo['detail'][$v['type']]['amount'] = $realAmount <= 0 ? 0 : $realAmount;
            $total_pay_amount += $payAmount;
            $arr = array_merge($arr, $charge_info);
            $fees[] = $arr;
        }
        // 记录应付总金额
        $setPayAmount = 0;
        if (!empty($uuidInfo['detail'])) {
            array_map(function ($m) use (&$setPayAmount) {
                $setPayAmount += $m['amount'];
            }, $uuidInfo['detail']);
        }
        $uuidInfo['total_pay_amount'] = $setPayAmount;
        $chargeUuid = json_encode($uuidInfo, JSON_UNESCAPED_UNICODE);
        $this->redis->setex(OrderModel::PAY_AMOUNT_REDIS_KEY . $params['charge_uuid'], 360, $chargeUuid);

        // 记录应付总金额
        $total_pay_amount = round($total_pay_amount / 100, 2);
        return [
            'charge_uuid' => $params['charge_uuid'],
            'total_pay_amount' => $total_pay_amount,
            'charge_detail' => $fees
        ];
    }

    /**
     * @param $params
     * @return mixed
     * 物业费业务逻辑
     */
    private function property_management_fee($params)
    {
        if (isTrueKey($params, 'project_id', 'house_id') == false) {
            rsp_die_json(10001, '参数缺失');
        }
        if (!isset($params['data_type'])) {
            rsp_die_json(10001, '参数缺失');
        }

        //查询项目信息
        $project_show = $this->pm->post('/project/show', ['project_id' => $params['project_id']]);
        if ($project_show['code'] != 0 || !$project_show['content']) {
            rsp_die_json(10002, '项目信息不存在');
        }

        //查询房产信息
        $house_show = $this->pm->post('/house/show', [
            'project_id' => $params['project_id'],
            'house_id' => $params['house_id'],
        ]);
        if ($house_show['code'] != 0 || !$house_show['content']) {
            rsp_die_json(10002, '房子信息存在');
        }

        $return = ['pay_amount' => 0, 'charge_info' => [], 'coupon_amount' => 0];

        $params['toll_system_tag_id'] = (int)$project_show['content']['toll_system_tag_id'];
        //项目业务配置未配置具体收费系统
        if (1386 === (int)$project_show['content']['toll_system_tag_id']) {
            log_message(__METHOD__ . '---该项目【' . $project_show['content']['project_name'] . '】未配置收费系统-----');
            rsp_die_json(10002, '未配置缴费信息，请联系管理员~');
        }

        //查费走极致收费系统
        if (1388 === (int)$project_show['content']['toll_system_tag_id']) {
            $jz_params = [
                'project_id' => $project_show['content']['project_id'],
                'house_id' => $house_show['content']['house_id'],
                'house_collect_penalty' => $house_show['content']['house_collect_penalty']
            ];
            if (isTrueKey($params, 'time_begin')) $jz_params['time_begin'] = $params['time_begin'];
            if (isTrueKey($params, 'time_end')) $jz_params['time_end'] = $params['time_end'];
            return $this->jz_fee($jz_params, $project_show);

        } else if (1399 == (int)$project_show['content']['toll_system_tag_id']) {
            //计费系统
            return $this->jf_sqy($house_show, $project_show);
        }

        $rsp = $this->adapter->post('/charge/detail', $params);
        if ((int)$rsp['code'] != 0) {
            log_message(__METHOD__ . '--pay_adapter---异常信息:' . $rsp['message']);
            rsp_die_json(10002, '查费失败，请稍后重试~');
        }

        if (empty($rsp['content']['charge_detail'])) {
            return $return;
        }

        //判断是否需要收取违约金
        if ($house_show['content']['house_collect_penalty'] == 'Y') {
            $params['collect_penalty'] = true;
            $rsp['content']['real_charge_total'] = $rsp['content']['charge_total'];
        } else {
            $params['collect_penalty'] = false;
        }
        $attach = $rsp['content']['attach'];
        $dates = [];
        foreach ($attach as $k => $v) {
            $$k = !empty($rsp['content']['charge_detail'][$k]) ? many_array_column($rsp['content']['charge_detail'][$k], 'date') : [];
            $tmp = array_column($$k, 'date');
            $dates = array_merge($dates, $tmp);
        }

        $dates = array_unique($dates);
        sort($dates);

        $tmp = [];
        foreach ($dates as $v) {
            foreach ($attach as $key => $value) {

                if (isset($$key[$v])) {
                    $tmp_arr = $$key[$v];
                    $tmp[$v][] = ['ChargeItem' => $value, 'arrearsMoney' => $tmp_arr['arrearsMoney'], 'penaltyMoney' => $tmp_arr['penaltyMoney']];
                }

            }
            $tmp[$v]['sec_charge_total'] = sprintf("%.2f", array_sum(array_column($tmp[$v], 'arrearsMoney')));
            $tmp[$v]['total_penalty'] = sprintf("%.2f", array_sum(array_column($tmp[$v], 'penaltyMoney')));
        }
        $rsp['content']['charge_detail'] = $tmp;
        $rsp['content']['attach'] = $params;

        // 判断减免金额 1370部分减免
        $feeReduceId = $project_show['content']['fee_reduce_tag_id'] ?? 0;
        $reduceAmount = $feeReduceId == 1370 ? ($project_show['content']['reduce_amount'] ?? 0) : 0;
        $return = [
            'pay_amount' => $rsp['content']['real_charge_total'],
            'charge_info' => $rsp['content'],
            'coupon_amount' => $reduceAmount
        ];
        return $return;
    }

    /**
     * 停车费业务逻辑
     */
    private function station_fee($params)
    {
        $return = ['pay_amount' => 0, 'is_show_url' => false, 'charge_info' => [], 'coupon_amount' => 0];
        if (!isTrueKey($params, 'project_id', 'mobile')) {
            return $return;
        }

        // 检测是否联动停车费 1307：EP停车
        $show = $this->pm->post('/project/show', ['project_id' => $params['project_id']]);
        if ($show['code'] != 0 || empty($show['content'])) {
            return $return;
        }
        $project = $show['content'];
        if ($project['linkage_payment'] != 'Y' || $project['ownership_company_tag_id'] != 1307) {
            return $return;
        }

        // 不计费标识  false 查询计费，但总金额不计
        $selectContracts = $params['select_contracts'] ?? false;
        if (is_array($selectContracts) && empty($selectContracts)) {
            return $return;
        }
        if (!empty($selectContracts)) {
            $arr = [];
            array_map(function ($m) use (&$arr) {
                $contractId = $m['contract_id'] ?? 0;
                $contractId ? $arr[$contractId] = $m : [];
            }, $selectContracts);
            $selectContracts = $arr;
        }

        // 请求车场适配器月卡计费接口
        $fees = $this->station_adapter->post('/ep/contract/feeLists', $params);
        $feeInfo = $fees['content'] ?: [];
        if ($fees['code'] != 0 || empty($feeInfo)) {
            $return['is_show_url'] = $fees['code'] == 4000 ? true : false;
            return $return;
        }

        $payAmount = 0;
        $jsfrom = $_SESSION['jsfrom'] ?: $_SESSION['member_jsfrom'];
        $feeData = array_map(function ($m) use (&$payAmount, $jsfrom, $selectContracts) {
            $contractId = $m['contract_info']['contract_id'] ?? 0;
            $isCharging = $selectContracts[$contractId]['selected'] ?? 'Y';
            $amount = $isCharging == "Y" ? ($m['fee_info']['amount'] ?? 0) : 0;
            $payAmount += (int)bcmul($amount, 100);
            $m['contract_info']['jsfrom'] = $jsfrom;
            return $m;
        }, $feeInfo);

        $return = [
            'pay_amount' => false === $selectContracts ? 0 : round($payAmount / 100, 2),
            'is_show_url' => false,
            'charge_info' => $feeData,
            'coupon_amount' => 0
        ];
        return $return;
    }


    /**
     * 极致适配器费用查询
     * @param array $params
     * @param $project_show
     * @return array
     */
    private function jz_fee($params = [], $project_show)
    {
        $return = ['pay_amount' => 0, 'charge_info' => [], 'coupon_amount' => 0];
        $collect_penalty = $params['house_collect_penalty'] == 'Y' ? true : false;
        unset($params['house_collect_penalty']);

        $jz_house_show = $this->jz->post('/house/show', ['house_id' => $params['house_id']]);
        if (!$jz_house_show || 0 !== (int)$jz_house_show['code']) {
            rsp_die_json(10002, '未配置缴费信息，请联系管理员~');
        }


        $jz_detail = $this->jz->post('/jz/detail', $params);
        if (!$jz_detail || $jz_detail['code'] != 0) {
            log_message(__METHOD__ . '---极致收费系统查费失败' . $jz_detail['message'] ?? '系统错误' . '---房间id:' . $params['house_id']);
            rsp_die_json(10002, '查费失败，请稍后重试~');
        }

        if (empty($jz_detail['content'])) {
            log_message(__METHOD__ . '---没有产生费用--房间号id:' . $params['house_id']);
            return $return;
        }

        $jz_detail = array_map(function ($m) {
            $m['date'] = date('Y.m', strtotime($m['shouldDate']));
            return $m;
        }, $jz_detail['content']);

        $dates = array_unique(array_column($jz_detail, 'date'));
        sort($dates);

        $data = [];
        $data_type = 0;
        $charge_total = $charge_penalty_total = 0;
        foreach ($dates as $date) {
            $sec_charge_total = $total_penalty = 0;
            foreach ($jz_detail as $jd) {
                if ($date == $jd['date']) {
                    $tmp_amount = bcsub($jd['amount'], $jd['latefee']);
                    $amount = bcdiv($tmp_amount, 100, 2);
                    $penalty_money = bcdiv($jd['latefee'], 100, 2);

                    $sec_charge_total = bcadd($sec_charge_total, $amount, 2);
                    $total_penalty = bcadd($total_penalty, $penalty_money, 2);
                    $data[$date][] = ['ChargeItem' => $jd['tollItemName'], 'arrearsMoney' => $amount, 'penaltyMoney' => $penalty_money];
                }
            }

            $data[$date]['sec_charge_total'] = $sec_charge_total;
            $data[$date]['total_penalty'] = $total_penalty;

            $charge_total = bcadd($charge_total, $sec_charge_total, 2);
            $charge_total = bcadd($charge_total, $total_penalty, 2);
            $charge_penalty_total = bcadd($charge_penalty_total, $total_penalty, 2);
        }

        $real_charge_total = $charge_total;
        //收取违约金
        if (!$collect_penalty) {
            $real_charge_total = bcsub($charge_total, $charge_penalty_total, 2);
        }


        // 判断减免金额 1370部分减免
        $feeReduceId = $project_show['content']['fee_reduce_tag_id'] ?? 0;
        $reduceAmount = $feeReduceId == 1370 ? ($project_show['content']['reduce_amount'] ?? 0) : 0;
        $return = [
            'pay_amount' => $real_charge_total,
            'charge_info' => [
                'data_type' => $data_type,
                'charge_total' => $charge_total,
                'real_charge_total' => $real_charge_total,
                'charge_penalty_total' => $charge_penalty_total,
                'charge_detail' => $data,
                'charge_coupon_detail' => [],
                'attach' => array_merge($params, ['collect_penalty' => $collect_penalty, 'toll_system_tag_id' => $project_show['content']['toll_system_tag_id']])
            ],
            'coupon_amount' => $reduceAmount
        ];
        return $return;
    }


    public function jf_sqy($house, $project)
    {
        try {
            $data_type = 0;
            $return = ['pay_amount' => 0, 'charge_info' => [], 'coupon_amount' => 0];
            $collect_penalty = $house['content']['house_collect_penalty'] == 'Y' ? true : false;

            $house_show = $this->pm->post('/house/show', [
                'house_id' => $house['content']['house_id'],
            ]);
            if (!$house_show || 0 !== (int)$house_show['code'] || empty($house_show['content'])) {
                rsp_die_json(10002, '房产信息不存在');
            }

            $receivable_lists = $this->cost->post('/receivableBill/lists', [
                'space_id' => $house_show['content']['space_id'],
                'billing_status_tag_id' => 1506,
            ]);
            if (!$receivable_lists || 0 !== (int)$receivable_lists['code']) {
                rsp_die_json(10002, '账单信息查询失败');
            }
            if (empty($receivable_lists['content'])) {
                return $return;
            }

            $creations = array_map(function ($m) {
                return date('Y.m', strtotime($m));
            }, array_column($receivable_lists['content'], 'create_time'));

            $dates = array_unique($creations);
            sort($dates);

            $data = [];
            $charge_total = $charge_penalty_total = 0;
            $receivable_bill_ids = array_column($receivable_lists['content'], 'receivable_bill_id');
            foreach ($dates as $date) {
                $sec_charge_total = $total_penalty = 0;
                foreach ($receivable_lists['content'] as $sad) {
                    if ($date == date('Y.m', strtotime($sad['create_time']))) {
                        $amount = $sad['billing_amount'];
                        $penalty_money = $sad['billing_penalty_amount'];

                        $sec_charge_total = bcadd($sec_charge_total, $amount, 2);
                        $total_penalty = bcadd($total_penalty, $penalty_money, 2);

                        $data[$date][] = ['ChargeItem' => $sad['billing_account_name'], 'arrearsMoney' => $amount, 'penaltyMoney' => $penalty_money];
                    }
                }
                $data[$date]['sec_charge_total'] = $sec_charge_total;
                $data[$date]['total_penalty'] = $total_penalty;

                $charge_total = bcadd($charge_total, $sec_charge_total, 2);
                $charge_total = bcadd($charge_total, $total_penalty, 2);
                $charge_penalty_total = bcadd($charge_penalty_total, $total_penalty, 2);
            }
            $real_charge_total = $charge_total;
            //收取违约金
            if (!$collect_penalty) {
                $real_charge_total = bcsub($charge_total, $charge_penalty_total, 2);
            }

            // 判断减免金额 1370部分减免
            $feeReduceId = $project_show['content']['fee_reduce_tag_id'] ?? 0;
            $reduceAmount = $feeReduceId == 1370 ? ($project_show['content']['reduce_amount'] ?? 0) : 0;
            $return = [
                'pay_amount' => $real_charge_total,
                'charge_info' => [
                    'data_type' => $data_type,
                    'charge_total' => $charge_total,
                    'real_charge_total' => $real_charge_total,
                    'charge_penalty_total' => $charge_penalty_total,
                    'charge_detail' => $data,
                    'charge_coupon_detail' => [],
                    'attach' => array_merge(['receivable_bill_ids' => $receivable_bill_ids, 'space_id' => $house_show['content']['space_id']], ['collect_penalty' => $collect_penalty, 'toll_system_tag_id' => $project['content']['toll_system_tag_id']])
                ],
                'coupon_amount' => $reduceAmount
            ];
            return $return;

        } catch (\Exception $e) {
            log_message(__METHOD__ . '----查费异常信息-----------' . $e->getMessage());
        }

    }

}
