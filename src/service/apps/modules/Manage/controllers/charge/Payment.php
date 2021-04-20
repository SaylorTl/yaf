<?php
use Project\ArrearsModel;
final class Payment extends Base
{
    public function payOrder($params = [])
    {
        $this->checkPayAmount($params);
        if($params['trade_type_tag_id'] == '1619'){
            $this->cashPayOrder($params);
        }else{
            $this->wechatPayOrder($params);
        }
    }

    public function wechatPayOrder($params = [])
    {
        $fields = ['project_id','trade_type_tag_id', 'amount', 'total_amount','sub_orders', 'space_id','product_id'];
        if (!isTrueKey($params, ...$fields)) rsp_error_tips(10001);
        $mch = $this->pm->post('/project/mch/show', ['project_id' => $params['project_id']]);
        $mch = ($mch['code'] === 0 && $mch['content']) ? $mch['content'] : [];
        if (!$mch) rsp_error_tips(10002, '商户');
        $params['total_amount'] = $params['total_amount'] * 100 / 100;
        $params['amount'] = $params['amount'] * 100 / 100;
        $order_notify_url = getConfig('ms.ini')->get('order_notify.url');
        $attach = !empty($params['attach'])?json_decode($params['attach'], true):[];
        $sub_title = '';
        $trade_source_tag_arr = array_column($params['sub_orders'], 'trade_source_tag_id');
        $trade_tag_res = $this->tag->post('/tag/lists', ['tag_ids' => implode(',', $trade_source_tag_arr),'nolevel'=>'Y']);
        if ($trade_tag_res['code'] != 0) {
            rsp_die_json(10002, $trade_tag_res['message']);
        }
        $trade_tag_content = array_column($trade_tag_res['content'], null, 'tag_id');
        if (!empty($params['sub_orders'])) {
            foreach ($params['sub_orders'] as $k => $v) {
                $tnum = OrderModel::new_order_sn();
                $v['attach'] = !empty($v['attach'])?json_decode($v['attach'],true):[];
                $params['sub_orders'][$k]['attach'] = $params['attach'];
                $params['sub_orders'][$k]['tnum'] = $tnum;
                $params['sub_orders'][$k]['total_amount'] = $v['total_amount'] * 100 / 100;
                $params['sub_orders'][$k]['amount'] = $v['amount'] * 100 / 100;
                $params['sub_orders'][$k]['notify_url'] = $order_notify_url;
                $params['sub_orders'][$k]['body'] = !empty($v['body'])?$v['body']:[];
                $params['sub_orders'][$k]['charge_body'] = !empty($v['body'])?$v['body']:'';
                $sub_title =!empty($sub_title)? $sub_title ."_".$trade_tag_content[$v['trade_source_tag_id']]['tag_name'] ."_".$v['body']
                    : $trade_tag_content[$v['trade_source_tag_id']]['tag_name'] ."_".$v['body'];
                foreach ($params['sub_orders'][$k]['detail'] as $kj => $vj) {
                    $params['sub_orders'][$k]['detail'][$kj]['total_amount'] = !empty($vj['total_amount']) ? round($vj['total_amount'] * 100) / 100 : 0;
                    $params['sub_orders'][$k]['detail'][$kj]['amount'] = !empty($vj['amount']) ? round($vj['amount'] * 100) / 100 : 0;
                    $params['sub_orders'][$k]['detail'][$kj]['penal_amout'] = !empty($vj['penal_amout']) ? round($vj['penal_amout'] * 100) / 100 : 0;
                    $params['sub_orders'][$k]['detail'][$kj]['penal_total_amout'] = !empty($vj['penal_total_amout']) ? round($vj['penal_total_amout'] * 100) / 100 : 0;
                }
            }
        }
        $data = [
            'yhy_mch_id' => $mch['yhy_mch_id'],
            'third_app_id' => $_SESSION['third_party_app_id'],
            'trade_type_tag_id' => $params['trade_type_tag_id'],
            'amount' => $params['amount'],
            'total_amount' => $params['total_amount'],
            'success_url' => $params['success_url'],
            'fail_url' => $params['fail_url'],
            'attach' => !empty($attach['address']) ? ['address' => $attach['address']] : [],
            'ip' => $params['ip'] ?? '',
            'location' => $params['location'] ?? '',
            'product_id' => $params['product_id'] ?? '',
            'sub_orders' => $params['sub_orders'],
            'body' => $sub_title,
            'created_by' => !empty($params['create_client_id']) ? $params['create_client_id'] : $this->client_id,
        ];
        // sign
        $sign = $this->getSign($data, base64_decode($mch['yhy_mch_pri_key']));
        $data['sign'] = $sign;
        $res = Comm_Pay::gateway('app.payment.unified', $data);
        if ($res['code'] != 0) {
            rsp_error_tips($res['code'], $res['message']);
        }
        $sub_orders = array_column($params['sub_orders'], null, 'tnum');
        $tag_res = $this->tag->post('/tag/lists', ['type_id' => '136']);
        if ($tag_res['code'] != 0) {
            rsp_error_tips($tag_res['code'], $tag_res['message']);
        }
        $app_config = array_column($tag_res['content'], null, 'tag_val');
        $project_res  = $this->pm->post('/project/show', ['project_id'=>$params['project_id']]);
        if ($project_res['code'] != 0) {
            rsp_die_json('10002', '项目信息查询失败');
        }
        if (!empty($res['content'])) {
            foreach ($res['content']['sub_tnums'] as $kl => $vl) {
                $sub_orders[$vl['tnum']]['toll_system_tag_id'] = $project_res['content']['toll_system_tag_id'] ?? '';
                $sub_orders[$vl['tnum']]['out_trade_tnum'] = $vl['out_trade_tnum'];
                $sub_orders[$vl['tnum']]['business_tnum'] = $res['content']['tnum'];
                $sub_orders[$vl['tnum']]['channel_tag_id'] = '1624';
                $sub_orders[$vl['tnum']]['charge_source_tag_id'] = $app_config[$_SESSION['member_jsfrom_wx_appid']]['tag_id'] ?? '';
                $sub_orders[$vl['tnum']]['jsfrom_id'] = $_SESSION['jsfrom_id'] ?? '';
                $sub_orders[$vl['tnum']]['trade_type_tag_id'] = $params['trade_type_tag_id'];
                $sub_orders[$vl['tnum']]['order_status_tag_id'] = self::PAY_STATUS['未支付'];
                $sub_orders[$vl['tnum']]['project_id'] = $params['project_id'] ?? '';
                $sub_orders[$vl['tnum']]['space_id'] = $params['space_id'] ?? '';
                $sub_orders[$vl['tnum']]['cell_id'] = $params['cell_id'] ?? '';
                $sub_orders[$vl['tnum']]['remark'] = $params['remark'] ?? '';
                $sub_orders[$vl['tnum']]['client_id'] = $this->client_id;
                $sub_orders[$vl['tnum']]['create_client_id'] = $params['create_client_id'] ?? "";
                $sub_orders[$vl['tnum']]['sender_client_id'] = $_SESSION['sender_client_id'] ?? '';

                if (!empty($sub_orders[$vl['tnum']]['detail'])) {
                    foreach ($sub_orders[$vl['tnum']]['detail'] as $kk => $vk) {
                        $sub_orders[$vl['tnum']]['detail'][$kk]['sub_tnum'] = OrderModel::new_order_sn();
                        $sub_orders[$vl['tnum']]['detail'][$kk]['out_trade_tnum'] = $vl['out_trade_tnum'];
                    }
                }
            }
        }
        log_message('----payOrder----' . json_encode($sub_orders));
        $result = $this->order->post('/order/gene_order', $sub_orders);
        if ($result['code'] !== 0) {
            rsp_die_json(10002, $result['message']);
        }
        $es_result = Comm_EventTrigger::push('gene_es_order',['data'=>$sub_orders,'app_id'=>$_SESSION['oauth_app_id']]);
        if (empty($es_result)){
            rsp_die_json(10002, $es_result['message']);
        }
        rsp_success_json(["business_tnum"=>$res['content']['tnum'],"qr_code"=>$res['content']['qr_code']]);
    }

    public function cashPayOrder($params = [])
    {
        $fields = ['project_id', 'trade_type_tag_id', 'amount', 'total_amount','sub_orders', 'space_id'];
        if (!isTrueKey($params, ...$fields)) rsp_error_tips(10001);
        $mch = $this->pm->post('/project/mch/show', ['project_id' => $params['project_id']]);
        $mch = ($mch['code'] === 0 && $mch['content']) ? $mch['content'] : [];
        if (!$mch) rsp_error_tips(10002, '商户');
        $params['total_amount'] = $params['total_amount'] * 100 / 100;
        $params['amount'] = $params['amount'] * 100 / 100;
        $order_notify_url = getConfig('ms.ini')->get('order_notify.url');
        $attach = !empty($params['attach'])?json_decode($params['attach'], true):[];
        $sub_title = '';
        $trade_source_tag_arr = array_column($params['sub_orders'], 'trade_source_tag_id');
        $trade_tag_res = $this->tag->post('/tag/lists', ['tag_ids' => implode(',', $trade_source_tag_arr),'nolevel'=>'Y']);
        if ($trade_tag_res['code'] != 0) {
            rsp_die_json(10002, $trade_tag_res['message']);
        }
        $trade_tag_content = array_column($trade_tag_res['content'], null, 'tag_id');
        if (!empty($params['sub_orders'])) {
            foreach ($params['sub_orders'] as $k => $v) {
                $tnum = OrderModel::new_order_sn();
                $v['attach'] = !empty($v['attach'])?json_decode($v['attach'],true):[];
                $params['sub_orders'][$k]['attach'] = $params['attach'];
                $params['sub_orders'][$k]['tnum'] = $tnum;
                $params['sub_orders'][$k]['total_amount'] = $v['total_amount'] * 100 / 100;
                $params['sub_orders'][$k]['amount'] = $v['amount'] * 100 / 100;
                $params['sub_orders'][$k]['notify_url'] = $order_notify_url;
                $params['sub_orders'][$k]['body'] = !empty($v['body'])?$v['body']:[];
                $params['sub_orders'][$k]['charge_body'] = !empty($v['body'])?$v['body']:'';
                $sub_title =!empty($sub_title)? $sub_title ."_".$trade_tag_content[$v['trade_source_tag_id']]['tag_name'] ."_".$v['body']
                    : $trade_tag_content[$v['trade_source_tag_id']]['tag_name'] ."_".$v['body'];
                foreach ($params['sub_orders'][$k]['detail'] as $kj => $vj) {
                    $params['sub_orders'][$k]['detail'][$kj]['total_amount'] = !empty($vj['total_amount']) ? round($vj['total_amount'] * 100) / 100 : 0;
                    $params['sub_orders'][$k]['detail'][$kj]['amount'] = !empty($vj['amount']) ? round($vj['amount'] * 100) / 100 : 0;
                    $params['sub_orders'][$k]['detail'][$kj]['penal_amout'] = !empty($vj['penal_amout']) ? round($vj['penal_amout'] * 100) / 100 : 0;
                    $params['sub_orders'][$k]['detail'][$kj]['penal_total_amout'] = !empty($vj['penal_total_amout']) ? round($vj['penal_total_amout'] * 100) / 100 : 0;
                }
            }
        }
        $data = [
            'yhy_mch_id' => $mch['yhy_mch_id'],
            'trade_type_tag_id' => '1619',
            'amount' => $params['amount'],
            'total_amount' => $params['total_amount'],
            'attach' => !empty($attach['address']) ? ['address' => $attach['address']] : [],
            'ip' => $params['ip'] ?? '',
            'location' => $params['location'] ?? '',
            'sub_orders' => $params['sub_orders'],
            'created_by' => !empty($params['create_client_id']) ? $params['create_client_id'] : $this->client_id,
            'body' => $sub_title,
        ];
        // sign
        $sign = $this->getSign($data, base64_decode($mch['yhy_mch_pri_key']));
        $data['sign'] = $sign;

        $res = Comm_Pay::gateway('app.payment.unified', $data);
        if ($res['code'] != 0) {
            rsp_error_tips($res['code'], $res['message']);
        }
        $sub_orders = array_column($params['sub_orders'], null, 'tnum');
        $tag_res = $this->tag->post('/tag/lists', ['type_id' => '136']);
        if ($tag_res['code'] != 0) {
            rsp_error_tips($tag_res['code'], $tag_res['message']);
        }
        $app_config = array_column($tag_res['content'], null, 'tag_val');
        $project_res  = $this->pm->post('/project/show', ['project_id'=>$params['project_id']]);
        if ($project_res['code'] != 0) {
            rsp_die_json('10002', '项目信息查询失败');
        }
        if (!empty($res['content'])) {
            foreach ($res['content']['sub_tnums'] as $kl => $vl) {
                $sub_orders[$vl['tnum']]['toll_system_tag_id'] = $project_res['content']['toll_system_tag_id'] ?? '';
                $sub_orders[$vl['tnum']]['out_trade_tnum'] = $vl['out_trade_tnum'];
                $sub_orders[$vl['tnum']]['business_tnum'] = $res['content']['tnum'];
                $sub_orders[$vl['tnum']]['channel_tag_id'] = '1625';
                $sub_orders[$vl['tnum']]['charge_source_tag_id'] = $app_config[$_SESSION['member_jsfrom_wx_appid']]['tag_id'] ?? '';
                $sub_orders[$vl['tnum']]['jsfrom_id'] = $_SESSION['jsfrom_id'] ?? '';
                $sub_orders[$vl['tnum']]['trade_type_tag_id'] = $params['trade_type_tag_id'];
                $sub_orders[$vl['tnum']]['order_status_tag_id'] = self::PAY_STATUS['未支付'];
                $sub_orders[$vl['tnum']]['project_id'] = $params['project_id'] ?? '';
                $sub_orders[$vl['tnum']]['space_id'] = $params['space_id'] ?? '';
                $sub_orders[$vl['tnum']]['cell_id'] = $params['cell_id'] ?? '';
                $sub_orders[$vl['tnum']]['remark'] = $params['remark'] ?? '';
                $sub_orders[$vl['tnum']]['client_id'] = $this->client_id;
                $sub_orders[$vl['tnum']]['create_client_id'] = $params['create_client_id'] ?? "";
                $sub_orders[$vl['tnum']]['sender_client_id'] = $_SESSION['sender_client_id'] ?? '';
                if (!empty($sub_orders[$vl['tnum']]['detail'])) {
                    foreach ($sub_orders[$vl['tnum']]['detail'] as $kk => $vk) {
                        $sub_orders[$vl['tnum']]['detail'][$kk]['sub_tnum'] = OrderModel::new_order_sn();
                        $sub_orders[$vl['tnum']]['detail'][$kk]['out_trade_tnum'] = $vl['out_trade_tnum'];
                    }
                }
            }
        }
        log_message('----payOrder----' . json_encode($sub_orders));
        $result = $this->order->post('/order/gene_order', $sub_orders);
        if ($result['code'] !== 0) {
            rsp_die_json(10002, $result['message']);
        }
        $es_result = Comm_EventTrigger::push('gene_es_order',['data'=>$sub_orders,'app_id'=>$_SESSION['oauth_app_id']]);
        if (empty($es_result)){
            rsp_die_json(10002, $es_result['message']);
        }
        $cost = new Comm_Curl([ 'service'=>'billing','format'=>'json']);
        $billparams['billing_status_tag_id'] = '1505';
        $billparams['receivable_bill_ids'] = $attach['receivable_bill_ids'];
        $billparams['paid_time'] = time();
        $billparams['tnum'] = $res['content']['tnum'];
        $billparams['updated_by'] = $_SESSION['employee_id'];
        $pay_res =  $cost->post( '/receivableBill/update', $billparams);
        if(empty($pay_res) ||  $pay_res['code']!=0){
            log_message('----casepayOrder--账单更新失败--' . json_encode($billparams));
        }
        //修改欠费记录
        ArrearsModel::handle(['space_id' => $params['space_id']]);
        rsp_success_json('支付成功');
    }

    private function getSign($params = [], $pri_key = '')
    {
        ksort($params);
        openssl_sign(json_encode($params), $sign, $pri_key);
        return base64_encode($sign);
    }
}