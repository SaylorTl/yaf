<?php

class Base
{
    const STATUS = [
        '已创建' => 585,
        '已支付' => 586,
    ];

    const ENABLED = [
        '启用' => 668,
        '禁用' => 669,
    ];

    const PAY_STATUS = [
        '未支付' => 682,
        '支付失败' => 683,
        '已支付' => 684,
        '已取消' => 685,
        '已关闭' => 686,
    ];

    protected $pm;

    protected $client_id;

    protected $employee_id;

    protected $user;

    protected $order;

    protected $company;

    protected $tag;

    public function __construct()
    {
        $this->pm = new Comm_Curl([ 'service'=>'pm','format'=>'json']);
        $this->user = new Comm_Curl([ 'service'=> 'user','format'=>'json']);
        $this->order = new Comm_Curl([ 'service'=> 'order','format'=>'json']);
        $this->tag = new Comm_Curl([ 'service'=> 'tag','format'=>'json']);
        $this->client_id = $_SESSION['client_id'] ?? 0;
        $this->company = new Comm_Curl([ 'service'=> 'company','format'=>'json']);
        $this->event_trigger = new Comm_Curl([ 'service'=> 'event_trigger','format'=>'json']);
        $this->project_id = $_SESSION['member_project_id'] ?? '';
    }

    /**
     * @param $params
     * 检测下单总实付金额
     */
    public function checkPayAmount($params){
        if (!isTrueKey($params, 'amount', 'sub_orders')) {
            rsp_error_tips(10001);
        }

        $params['charge_uuid'] = $params['charge_uuid'] ?? '';
        // 校验应付总金额
        $redis = Comm_Redis::getInstance();
        $redis->select(8);
        $chargeUUid = $redis->get(OrderModel::PAY_AMOUNT_REDIS_KEY . $params['charge_uuid']);
        $chargeUUid = !empty($chargeUUid) ? json_decode($chargeUUid, true) : [];
        $orderTypes = array_unique(array_column($params['sub_orders'], 'trade_source_tag_id'));
        $total_pay_amount = 0;
        if (!empty($orderTypes)) {
            array_map(function ($m) use (&$total_pay_amount, $chargeUUid) {
                $total_pay_amount += $chargeUUid['detail'][$m]['amount'] ?? 0;
            }, $orderTypes);
        }
        $charge_types_methods = [
            1 => ['type' => 698, 'name' => '物业费', 'method' => 'property_management_fee'],
            4 => ['type' => 697, 'name' => '停车费', 'method' => 'station_fee'],
        ];
        $chargeTags = array_column($charge_types_methods, 'type');
        $arr = array_intersect($orderTypes, $chargeTags);
        if ((empty($chargeUUid) || $total_pay_amount > (int)$params['amount']) && !empty($arr)) {
            rsp_error_tips(10015, '应付金额');
        }
    }
}

