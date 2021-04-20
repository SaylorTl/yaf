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

    protected $user;

    protected $order;

    protected $company;

    protected $tag;

    protected $event_trigger;

    protected $contract;

    protected  $billing;

    protected  $rule;

    public function __construct()
    {
        $this->pm = new Comm_Curl([ 'service'=>'pm','format'=>'json']);
        $this->user = new Comm_Curl([ 'service'=> 'user','format'=>'json']);
        $this->order = new Comm_Curl([ 'service'=> 'order','format'=>'json']);
        $this->client_id = $_SESSION['client_id'] ?? 0;
        $this->sender_client_id = $_SESSION['sender_client_id'] ?? 0;
        $this->company = new Comm_Curl([ 'service'=> 'company','format'=>'json']);
        $this->tag = new Comm_Curl([ 'service'=>'tag','format'=>'json']);
        $this->event_trigger = new Comm_Curl([ 'service'=> 'event_trigger','format'=>'json']);
        $this->contract = new Comm_Curl([ 'service'=>'contract','format'=>'json']);
        $this->billing = new Comm_Curl(['service' => 'billing', 'format' => 'json',]);
        $this->rule = new Comm_Curl([ 'service'=>'rule','format'=>'json']);

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
        $chargeTags = array_column(Basecharging::CHARGE_TYPES_METHODS, 'type');
        $arr = array_intersect($orderTypes, $chargeTags);
        if ((empty($chargeUUid) || $total_pay_amount > (int)$params['amount']) && !empty($arr)) {
            rsp_error_tips(10015, '应付金额');
        }
    }
}

