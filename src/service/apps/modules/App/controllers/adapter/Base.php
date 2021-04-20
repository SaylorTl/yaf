<?php

class Base
{
    protected $adapter;
    protected $pm;
    protected $company;

    protected $station_adapter;
    protected $device;

    protected $tag;
    protected $user;
    protected $order;

    protected $jz;

    protected $cost;

    public function __construct()
    {
        $this->adapter = new Comm_Curl(['service' => 'adapter', 'format' => 'json']);
        $this->pm = new Comm_Curl(['service' => 'pm', 'format' => 'json']);
        $this->company = new Comm_Curl(['service' => 'company', 'format' => 'json']);

        $this->station_adapter = new Comm_Curl(['service' => 'station_adapter', 'format' => 'json']);
        $this->device = new Comm_Curl(['service' => 'device', 'format' => 'json']);
        $this->tag = new Comm_Curl(['service' => 'tag', 'format' => 'json']);

        $this->user = new Comm_Curl(['service' => 'user', 'format' => 'json']);
        $this->order = new Comm_Curl(['service' => 'order', 'format' => 'json']);

        $this->jz = new Comm_Curl(['service' => 'jz', 'format' => 'json']);

        $this->cost = new Comm_Curl(['service' => 'billing', 'format' => 'json']);

    }

    public function _getAttach($p_tnum)
    {
        try {
            //查询业务订单
            $order_show = $this->order->post('/order/show', [
                'business_tnum' => $p_tnum,
                'trade_source_tag_id' => 698, //物业费
            ]);
            if (!$order_show || $order_show['code'] != 0 || !$order_show['content']) {
                rsp_die_json(10002, '订单信息不存在');
            }
            if ($order_show['content']['order_status_tag_id'] != 684) {
                rsp_die_json(10002, '该笔订单未支付');
            }

            //查询子订单
            $sub_order_lists = $this->order->post('/suborder/lists', ['tnum' => $order_show['content']['tnum']]);
            if (!$sub_order_lists || $sub_order_lists['code'] != 0 || !$sub_order_lists['content']) {
                rsp_die_json(10002, '子订单信息查询失败');
            }

            $attach = json_decode($order_show['content']['attach'], true);
            $dates = [];
            foreach ($sub_order_lists['content'] as $sub) {
                $date = $sub['year'] . '.' . $sub['tnum_month'];
                array_push($dates, $date);
            }

            return ['attach' => $attach, 'dates' => array_unique($dates), 'order_show' => $order_show['content']];

        } catch (\Exception $e) {
            log_message(__METHOD__ . '---获取附加数据异常--' . $e->getMessage() . '---line:' . $e->getLine());
        }
    }

}