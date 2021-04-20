<?php

class Notification extends Base
{
    /**
     * 支付回调通知处理method 697：停车场 698：物业费
     */
    const NOTIFY_METHODS = [
        697 => 'epstation',
        698 => 'cancel_order'
    ];

    /**
     * @param $params
     * 通知业务平台
     */
    public function notification_service_platform($params)
    {
        if (!isTrueKey($params, 'tnum') || !is_string($params['tnum'])) {
            rsp_die_json(10001, '订单号缺失或错误');
        }
        $obj = new Comm_Curl(['service' => 'order', 'format' => 'json']);
        $order = $obj->post('/order/show', ['tnum' => $params['tnum']]);
        log_message(__METHOD__ . '---【订单】---' . json_encode($order, JSON_UNESCAPED_UNICODE));
        if (!$order || $order['code'] != 0) {
            rsp_die_json(10002, '查询订单失败');
        } elseif (empty($order['content'])) {
            rsp_die_json(10008, '未找到订单信息');
        }
        $sourceId = $order['content']['trade_source_tag_id'] ?? 0;
        $method = self::NOTIFY_METHODS[$sourceId] ?? null;
        if (!method_exists($this, $method)) {
            rsp_die_json(10007, '通知业务平台失败');
        }
        $this->$method($params);
    }

    /**
     * @param array $params
     * 停车场业务通知
     */
    public function epstation($params = [])
    {
        log_message(__METHOD__ . '---【订单回调通知】---' . json_encode($params, JSON_UNESCAPED_UNICODE));
        if (!isTrueKey($params, 'tnum') || !is_string($params['tnum'])) {
            rsp_die_json(10001, '订单号缺失或错误');
        }

        $order = $this->order->post('/order/show', ['tnum' => $params['tnum']]);
        log_message(__METHOD__ . '---【订单】---' . json_encode($order, JSON_UNESCAPED_UNICODE));
        $statusTag = $order['content']['order_status_tag_id'] ?? 0;
        if ($order['code'] != 0 || $statusTag != 684) {
            rsp_die_json(10001, '支付回调通知失败，请检查订单支付状态');
        }

        $notify = [
            'tnum' => $params['tnum'],
            'total_amount' => $order['content']['total_amount'],
            'amount' => $order['content']['amount'],
            'attach' => $order['content']['attach'],
            'status' => 'SUCCESS'
        ];
        $params['status'] = 'SUCCESS';
        $notify = $this->station_adapter->post('/ep/order/notify', $notify);
        if ($notify['code'] != 0) {
            rsp_die_json(10007, $notify['message']);
        }
        rsp_success_json($notify['content'], '请求成功');
    }

    /**
     * 销单
     * @param array $params
     */
    public function cancel_order($params = [])
    {
        log_message(__METHOD__ . '----' . json_encode($params));
        if (isTrueKey($params, 'business_tnum') === false || !is_string($params['business_tnum'])) {
            rsp_die_json(10001, '交易单号缺失或错误');
        }
        $time = rSnowFlake($params['business_tnum']);
        //获取订单附加信息
        $result = $this->_getAttach($params['business_tnum']);
        $attach = $result['attach'];
        $date1s = $result['dates'];
        $order_show = $result['order_show'];
        log_message('-xxxx111---' . json_encode([$attach]));
        if (empty($attach)) {
            rsp_die_json(10002, '附加数据有误');
        }
        if (!$date1s) {
            rsp_die_json(10002, '订单详情有误');
        }

        if (isset($attach['receivable_bill_ids'])) {
            //通知计费系统销单
            $this->notifyJfxt($order_show, $attach);
        }

        //查询下该房屋还在欠费
        $attach['charge_date'] = date('Y.m', $time);
        $charge_info = $this->adapter->post('/charge/detail', $attach);

        if (!$charge_info || $charge_info['code'] != 0) {
            $msg = isset($charge_info['message']) ? $charge_info['message'] : '';
            rsp_die_json(10002, '查费接口异常:' . $msg);
        }

        if ($charge_info['content']['charge_total'] == 0 || empty($charge_info['content']['charge_detail'])) {
            rsp_success_json('', '销单成功1');
        }

        $attach_data = $charge_info['content']['attach'];
        $dates = [];
        foreach ($attach_data as $k => $v) {
            $$k = !empty($charge_info['content']['charge_detail'][$k]) ? many_array_column($charge_info['content']['charge_detail'][$k], 'date') : [];
            $tmp = array_column($$k, 'date');
            $dates = array_merge($dates, $tmp);
        }

        $date2s = array_unique($dates);
        $diff_date = array_diff($date1s, $date2s);
        if ($diff_date) {
            rsp_die_json(10002, '消单月份不一致');
        }

        $channel = $order_show['channel_tag_id'] == '670' ? 'WECHAT' : 'QWXPAY';
        $notice_params = [
            'tnum' => $params['business_tnum'],
            'mch_id' => 1,
            'channel' => $channel,
            'paidtime' => $time,
            'total_amount' => $order_show['total_amount'],
            'amount' => $order_show['amount'],
            'attach' => json_encode($attach),
            'status' => 'SUCCESS',
        ];
        $notice_rsp = $this->adapter->post('/pay/notice', json_encode($notice_params), ['content-type:application/json']);
        if (!$notice_rsp || $notice_rsp['status'] != 'SUCCESS') rsp_die_json(10005, '销单失败');

        //触发自动汇总
        $result = $this->adapter->post('/bill/summary', []);
        log_message('-----/bill/summary--' . json_encode($result, JSON_UNESCAPED_UNICODE));

        //修改欠费列表记录
        $this->pm->post('/arrears/updateV2', [
            'project_id' => $attach['project_id'],
            'house_id' => $attach['house_id'],
            'arrears_month' => date('Y.m', $time)
        ]);
        rsp_success_json('', '销单成功2');
    }

    private function notifyJfxt($order_show, $attach)
    {
        $result = $this->cost->post('/pay/notice', [
            'receivable_bill_ids' => $attach['receivable_bill_ids'],
            'billing_status_tag_id' => 1505,
            'paid_time' => $order_show['paid_time'],
            'tnum' => $order_show['business_tnum']
        ]);

        if ($result['status'] === 'FAIL') {
            rsp_die_json(10002, '销单失败');
        }
        rsp_success_json(1, '销单成功');
    }
}


