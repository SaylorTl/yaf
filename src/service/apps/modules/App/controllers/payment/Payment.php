<?php

include __DIR__.'/../adapter/Basecharging.php';

final class Payment extends Base
{
    public function unified($params = [])
    {
        $fields = [
            'project_id', 'third_app_id', 'trade_type_tag_id',
            'amount', 'total_amount', 'success_url', 'fail_url', 'sub_orders'
        ];
        if (!isTrueKey($params, ...$fields)) rsp_error_tips(10001);

        // project_mch
        $mch = $this->pm->post('/project/mch/show', ['project_id' => $params['project_id']]);
        $mch = ($mch['code'] === 0 && $mch['content']) ? $mch['content'] : [];
        if (!$mch) rsp_error_tips(10002, '商户');

        // openid
        $openid = $this->user->post('/client/show', ['client_id' => $this->client_id]);
        $openid = ($openid['code'] === 0 && $openid['content']) ? $openid['content'] : [];
        $openid = $openid['openid'] ?? '';
        // data
        $data = [
            'yhy_mch_id' => $mch['yhy_mch_id'],
            'third_app_id' => $params['third_app_id'],
            'trade_type_tag_id' => $params['trade_type_tag_id'],
            'client_id' => $this->client_id,
            'amount' => $params['amount'],
            'total_amount' => $params['total_amount'],
            'success_url' => $params['success_url'],
            'fail_url' => $params['fail_url'],
            'sub_orders' => $params['sub_orders'],
            'ip' => $params['ip'] ?? '',
            'location' => $params['location'] ?? '',
            'openid' => $openid,
            'product_id' => $params['product_id'] ?? '',
            'auth_code' => $params['auth_code'] ?? '',
            'attach' => isTrueKey($params,'attach') ? (is_array($params['attach']) ? $params['attach'] : json_decode($params['attach'], true)) : [],
            'created_by' => $params['created_by'] ?? '',
        ];

        // sign
        $sign = $this->getSign($data, base64_decode($mch['yhy_mch_pri_key']));
        $data['sign'] = $sign;

        $res = Comm_Pay::gateway('app.payment.unified', $data);
        if ($res['code'] === 0) rsp_success_json($res['content']);
        rsp_error_tips($res['code'], $res['message']);
    }

    public function payOrder($params = [])
    {
        $this->checkPayAmount($params);
        $client_res = $this->user->post('/client/show', ['client_id' => $this->client_id]);
        if ($client_res['code'] != 0 || ($client_res['code'] == 0 && empty($client_res['content']))) {
            rsp_die_json(10002, "client_id查询失败");
        }
        $params['openid'] = $client_res['content']['openid'];
        if ('ALIPAY' == $client_res['content']['kind']) {
            $this->zfbPayOrder($params);
        }
        $this->wechatPayOrder($params);
    }

    public function wechatPayOrder($params = [])
    {
        $fields = ['project_id', 'third_app_id', 'trade_type_tag_id', 'amount', 'total_amount', 'success_url', 'fail_url',
            'sub_orders'];
        if (!isTrueKey($params, ...$fields)) rsp_error_tips(10001);
        $mch = $this->pm->post('/project/mch/show', ['project_id' => $params['project_id']]);
        $mch = ($mch['code'] === 0 && $mch['content']) ? $mch['content'] : [];
        if (!$mch) rsp_error_tips(10002, '商户');
        $params['total_amount'] = $params['total_amount'] * 100 / 100;
        $params['amount'] = $params['amount'] * 100 / 100;
        $order_notify_url = getConfig('ms.ini')->get('order_notify.url');
        $attach = json_decode($params['attach'], true);
        $sub_title = '';
        $trade_source_tag_arr = array_column($params['sub_orders'], 'trade_source_tag_id');
        $trade_tag_res = $this->tag->post('/tag/lists', ['tag_ids' => implode(',', $trade_source_tag_arr)]);
        if ($trade_tag_res['code'] != 0) {
            rsp_die_json(10002, $trade_tag_res['message']);
        }
        $trade_tag_content = array_column($trade_tag_res['content'], null, 'tag_id');
        if (!empty($params['sub_orders'])) {
            foreach ($params['sub_orders'] as $k => $v) {
                $tnum = OrderModel::new_order_sn();
                $params['sub_orders'][$k]['tnum'] = $tnum;
                $params['sub_orders'][$k]['total_amount'] = $v['total_amount'] * 100 / 100;
                $params['sub_orders'][$k]['amount'] = $v['amount'] * 100 / 100;
                $params['sub_orders'][$k]['notify_url'] = $order_notify_url;
                $params['sub_orders'][$k]['body'] = !empty($v['charge_body'])?$v['charge_body']:[];
                $sub_title =!empty($sub_title)? $sub_title ."_".$trade_tag_content[$v['trade_source_tag_id']]['tag_name'] ."_".$v['charge_body']
                    : $trade_tag_content[$v['trade_source_tag_id']]['tag_name'] ."_".$v['charge_body'];
                foreach ($params['sub_orders'][$k]['detail'] as $kj => $vj) {
                    $params['sub_orders'][$k]['detail'][$kj]['total_amount'] = !empty($vj['total_amount']) ? round($vj['total_amount'] * 10000) / 100 : 0;
                    $params['sub_orders'][$k]['detail'][$kj]['amount'] = !empty($vj['amount']) ? round($vj['amount'] * 10000) / 100 : 0;
                    $params['sub_orders'][$k]['detail'][$kj]['penal_amout'] = !empty($vj['penal_amout']) ? round($vj['penal_amout'] * 10000) / 100 : 0;
                    $params['sub_orders'][$k]['detail'][$kj]['penal_total_amout'] = !empty($vj['penal_total_amout']) ? round($vj['penal_total_amout'] * 10000) / 100 : 0;
                }
            }
        }
        $data = [
            'yhy_mch_id' => $mch['yhy_mch_id'],
            'third_app_id' => $_SESSION['third_party_app_id'],
            'trade_type_tag_id' => $params['trade_type_tag_id'],
            'amount' => $params['amount'],
            'client_id' => $this->client_id,
            'body' => $sub_title,
            'total_amount' => $params['total_amount'],
            'success_url' => $params['success_url'],
            'fail_url' => $params['fail_url'],
            'sub_orders' => $params['sub_orders'],
            'ip' => $params['ip'] ?? '',
            'location' => $params['location'] ?? '',
            'openid' => $params['openid'],
            'product_id' => $params['product_id'] ?? '',
            'auth_code' => $params['auth_code'] ?? '',
            'attach' => !empty($attach['address']) ? ['address' => $attach['address']] : [],
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
            rsp_error_tips('10002', '项目信息查询失败');
        }
        $project_station_res  = $this->pm->post('/project/stationcfg/show', ['project_id'=>$params['project_id']]);
        if ($project_station_res['code'] != 0) {
            rsp_error_tips('10002', '项目车场信息查询失败');
        }

        if (!empty($res['content'])) {
            foreach ($res['content']['sub_tnums'] as $kl => $vl) {
                $sub_orders[$vl['tnum']]['platform_type_tag_id'] = $project_station_res['content']['platform_type'] ?? '';
                $sub_orders[$vl['tnum']]['toll_system_tag_id'] = $project_res['content']['toll_system_tag_id'] ?? '';
                $sub_orders[$vl['tnum']]['out_trade_tnum'] = $vl['out_trade_tnum'];
                $sub_orders[$vl['tnum']]['business_tnum'] = $res['content']['tnum'];
                $sub_orders[$vl['tnum']]['channel_tag_id'] = $res['content']['config_used']['source_tag_id'];
                $sub_orders[$vl['tnum']]['charge_source_tag_id'] = $app_config[$_SESSION['jsfrom_wx_appid']]['tag_id'] ?? '';
                $sub_orders[$vl['tnum']]['jsfrom_id'] = $_SESSION['jsfrom_id'] ?? '';
                $sub_orders[$vl['tnum']]['trade_type_tag_id'] = $params['trade_type_tag_id'];
                $sub_orders[$vl['tnum']]['order_status_tag_id'] = self::PAY_STATUS['未支付'];
                $sub_orders[$vl['tnum']]['house_id'] = $params['house_id'] ?? '';
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
        if (!empty($params['qr_num'])) {
            $key = "etbase:wechat_qr:order_num:" . $params['qr_num'];
            $redis = Comm_Redis::getInstance();
            $redis->select(8);
            $redis->setex($key, 180, $res['content']['tnum']);
        }
        $redirect = $res['content']['redirect'];

        if (!empty($_SESSION['user_id'])) {
            $client_res = $this->user->post('/client/show', ['user_id' => $_SESSION['user_id'],
                'client_app_id' => $_SESSION['oauth_app_id'],
                'app_id' => $_SESSION['jsfrom_wx_appid']]);
            if ($client_res['code'] != 0) {
                rsp_die_json(10002, "用户client查询失败");
            }
            if (($client_res['code'] == 0 && empty($client_res['content']))) {
                $key = "etbase_wechat_qr_ordernum:" . $res['content']['tnum'];
                $redis = Comm_Redis::getInstance();
                $redis->select(8);
                $cacheData = json_encode(['user_id' => $_SESSION['user_id'],
                    'business_tnum' => $res['content']['tnum'],
                    'employee_id' => $_SESSION['employee_id'] ?? '',
                    'third_party_app_id' => $_SESSION['jsfrom_wx_appid'],
                    'auth_kind' => 'wechat',
                    'oauth_app_id' => $_SESSION['oauth_app_id'],
                    'pay_url' => $redirect]);
                $redis->setex($key, 1800, $cacheData);
                $auth_url = getConfig('ms.ini')->get('authjump.url');
                $back_url = urlencode("https://" . $_SERVER['HTTP_HOST'] . "/auth/client?business_tnum=" . $res['content']['tnum']);
                $redirect = $auth_url . '/jump?appid=' . $_SESSION['jsfrom_wx_appid'] . '&backurl=' . $back_url;
                rsp_success_json($redirect);
            }
            $order_bind_Params = ['business_tnum' => $res['content']['tnum'], 'sender_client_id' => $client_res['content']['client_id']];
            $update_result = $this->order->post('/order/update', $order_bind_Params);
            if ($update_result['code'] != 0) {
                rsp_die_json(10001, $update_result['message']);
            }
        }
        rsp_success_json($redirect);
    }

    public function zfbPayOrder($params = [])
    {
        $fields = ['project_id', 'third_app_id', 'trade_type_tag_id', 'amount', 'total_amount', 'success_url', 'fail_url',
            'sub_orders'];
        if (!isTrueKey($params, ...$fields)) rsp_error_tips(10001);

        $mch = $this->pm->post('/project/mch/show', ['project_id' => $params['project_id']]);
        $mch = ($mch['code'] === 0 && $mch['content']) ? $mch['content'] : [];
        if (!$mch) rsp_error_tips(10002, '商户');
        $params['total_amount'] = $params['total_amount'] * 100 / 100;
        $params['amount'] = $params['amount'] * 100 / 100;
        $order_notify_url = getConfig('ms.ini')->get('order_notify.url');
        $sub_title = '';
        $trade_source_tag_arr = array_column($params['sub_orders'], 'trade_source_tag_id');
        $trade_tag_res = $this->tag->post('/tag/lists', ['tag_ids' => implode(',', $trade_source_tag_arr)]);
        if ($trade_tag_res['code'] != 0) {
            rsp_die_json(10002, $trade_tag_res['message']);
        }
        $trade_tag_content = array_column($trade_tag_res['content'], null, 'tag_id');
        $attach = json_decode($params['attach'], true);
        if (!empty($params['sub_orders'])) {
            foreach ($params['sub_orders'] as $k => $v) {
                $tnum = OrderModel::new_order_sn();
                $params['sub_orders'][$k]['tnum'] = $tnum;
                $params['sub_orders'][$k]['total_amount'] = $v['total_amount'] * 100 / 100;
                $params['sub_orders'][$k]['amount'] = $v['amount'] * 100 / 100;
                $params['sub_orders'][$k]['notify_url'] = $order_notify_url;
                $params['sub_orders'][$k]['body'] = !empty($v['charge_body'])?$v['charge_body']:[];
                $sub_title =!empty($sub_title)? $sub_title ."_".$trade_tag_content[$v['trade_source_tag_id']]['tag_name'] ."_".$v['charge_body']
                    : $trade_tag_content[$v['trade_source_tag_id']]['tag_name'] ."_".$v['charge_body'];
                foreach ($params['sub_orders'][$k]['detail'] as $kj => $vj) {
                    $params['sub_orders'][$k]['detail'][$kj]['total_amount'] = !empty($vj['total_amount']) ? round($vj['total_amount'] * 10000) / 100 : 0;
                    $params['sub_orders'][$k]['detail'][$kj]['amount'] = !empty($vj['amount']) ? round($vj['amount'] * 10000) / 100 : 0;
                    $params['sub_orders'][$k]['detail'][$kj]['penal_amout'] = !empty($vj['penal_amout']) ? round($vj['penal_amout'] * 10000) / 100 : 0;
                    $params['sub_orders'][$k]['detail'][$kj]['penal_total_amout'] = !empty($vj['penal_total_amout']) ? round($vj['penal_total_amout'] * 10000) / 100 : 0;
                }
            }
        }

        $data = [
            'yhy_mch_id' => $mch['yhy_mch_id'],
            'third_app_id' => $_SESSION['third_party_app_id'],
            'trade_type_tag_id' => $params['trade_type_tag_id'],
            'amount' => $params['amount'],
            'client_id' => $this->client_id,
            'total_amount' => $params['total_amount'],
            'body' => $sub_title,
            'success_url' => $params['success_url'],
            'fail_url' => $params['fail_url'],
            'sub_orders' => $params['sub_orders'],
            'ip' => $params['ip'] ?? '',
            'location' => $params['location'] ?? '',
            'openid' => $params['openid'],
            'product_id' => $params['product_id'] ?? '',
            'auth_code' => $params['auth_code'] ?? '',
            'attach' => !empty($attach['address']) ? ['address' => $attach['address']] : [],
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
            rsp_error_tips('10002', '项目信息查询失败');
        }
        $project_station_res  = $this->pm->post('/project/stationcfg/show', ['project_id'=>$params['project_id']]);
        if ($project_station_res['code'] != 0) {
            rsp_error_tips('10002', '项目车场信息查询失败');
        }
        if (!empty($res['content'])) {
            foreach ($res['content']['sub_tnums'] as $kl => $vl) {
                $sub_orders[$vl['tnum']]['platform_type_tag_id'] = $project_station_res['content']['platform_type'] ?? '';
                $sub_orders[$vl['tnum']]['toll_system_tag_id'] = $project_res['content']['toll_system_tag_id'] ?? '';
                $sub_orders[$vl['tnum']]['out_trade_tnum'] = $vl['out_trade_tnum'];
                $sub_orders[$vl['tnum']]['business_tnum'] = $res['content']['tnum'];
                $sub_orders[$vl['tnum']]['channel_tag_id'] = $res['content']['config_used']['source_tag_id'];
                $sub_orders[$vl['tnum']]['charge_source_tag_id'] = $app_config[$_SESSION['jsfrom_wx_appid']]['tag_id'] ?? '';
                $sub_orders[$vl['tnum']]['jsfrom_id'] = $_SESSION['jsfrom_id'] ?? '';
                $sub_orders[$vl['tnum']]['trade_type_tag_id'] = $params['trade_type_tag_id'];
                $sub_orders[$vl['tnum']]['order_status_tag_id'] = self::PAY_STATUS['未支付'];
                $sub_orders[$vl['tnum']]['house_id'] = $params['house_id'] ?? '';
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
            rsp_error_tips($result['code'], $result['message']);
        }
        $es_result = Comm_EventTrigger::push('gene_es_order',['data'=>$sub_orders,'app_id'=>$_SESSION['oauth_app_id']]);
        if (empty($es_result)){
            rsp_die_json(10002, $es_result['message']);
        }
        if(!empty($params['qr_num'])){
            $key = "etbase:wechat_qr:order_num:".$params['qr_num'];
            $redis = Comm_Redis::getInstance();
            $redis->select(8);
            $redis->setex($key, 180, $res['content']['tnum']);
        }
        rsp_success_json($res['content']['redirect']);
    }

    public function cardPayOrder($params = [])
    {
        log_message('----cardPayOrder----' . json_encode($params));
        $fields = ['qr_num', 'project_id', 'third_app_id', 'trade_type_tag_id', 'amount', 'total_amount',
            'sub_orders', 'device_sn'];
        if (!isTrueKey($params, ...$fields)) rsp_error_tips(10001);

        // 校验应付总金额
        $this->checkPayAmount($params);

        $mch = $this->pm->post('/project/mch/show', ['project_id' => $params['project_id']]);
        $mch = ($mch['code'] === 0 && $mch['content']) ? $mch['content'] : [];
        if (!$mch) rsp_error_tips(10002, '商户');

        $order_notify_url = getConfig('ms.ini')->get('order_notify.url');
        $params['total_amount'] = $params['total_amount'] * 100 / 100;
        $params['amount'] = $params['amount'] * 100 / 100;
        $tnum = '';
        $trade_source_tag_arr = array_column($params['sub_orders'], 'trade_source_tag_id');
        $trade_tag_res = $this->tag->post('/tag/lists', ['tag_ids' => implode(',', $trade_source_tag_arr)]);
        if ($trade_tag_res['code'] != 0) {
            rsp_die_json(10002, $trade_tag_res['message']);
        }
        $trade_tag_content = array_column($trade_tag_res['content'], null, 'tag_id');
        $sub_title = '';
        if (!empty($params['sub_orders'])) {
            foreach ($params['sub_orders'] as $k => $v) {
                $tnum = OrderModel::new_order_sn();
                $params['sub_orders'][$k]['tnum'] = $tnum;
                $params['sub_orders'][$k]['notify_url'] = $order_notify_url;
                $params['sub_orders'][$k]['total_amount'] = $v['total_amount'] * 100 / 100;
                $params['sub_orders'][$k]['amount'] = $v['amount'] * 100 / 100;
//                $v['charge_body'] = empty($v['charge_body'])?'':$v['charge_body'];
//                $params['sub_orders'][$k]['body'] = !empty($v['charge_body'])?$v['charge_body']:[];
                $sub_title =!empty($sub_title)? $sub_title ."_".$trade_tag_content[$v['trade_source_tag_id']]['tag_name'] ."_".$v['charge_body']
                    : $trade_tag_content[$v['trade_source_tag_id']]['tag_name'] ."_".$v['charge_body'];

                foreach ($params['sub_orders'][$k]['detail'] as $kj => $vj) {
                    $params['sub_orders'][$k]['detail'][$kj]['total_amount'] = !empty($vj['total_amount']) ? round($vj['total_amount'] * 10000) / 100 : 0;
                    $params['sub_orders'][$k]['detail'][$kj]['amount'] = !empty($vj['amount']) ? round($vj['amount'] * 10000) / 100 : 0;
                    $params['sub_orders'][$k]['detail'][$kj]['penal_amout'] = !empty($vj['penal_amout']) ? round($vj['penal_amout'] * 10000) / 100 : 0;
                    $params['sub_orders'][$k]['detail'][$kj]['penal_total_amout'] = !empty($vj['penal_total_amout']) ? round($vj['penal_total_amout'] * 10000) / 100 : 0;
                }
            }
        }
        $attach = json_decode($params['attach'], true);
        $data = [
            'yhy_mch_id' => $mch['yhy_mch_id'],
            'third_app_id' => $params['third_app_id'],
            'trade_type_tag_id' => $params['trade_type_tag_id'],
            'created_by' => !empty($params['create_client_id']) ? $params['create_client_id'] : $this->client_id,
            'device_sn' => $params['device_sn'],
            'amount' => $params['amount'],
            'total_amount' => $params['total_amount'],
            'ip' => $params['ip'] ?? '',
            'location' => $params['location'] ?? '',
            'attach' => !empty($attach['address']) ? ['address' => $attach['address']] : [],
            'sub_orders' => $params['sub_orders'],
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
        $project_res  = $this->pm->post('/project/show', ['project_id'=>$params['project_id']]);
        if ($project_res['code'] != 0) {
            rsp_error_tips('10002', '项目信息查询失败');
        }
        $project_station_res  = $this->pm->post('/project/stationcfg/show', ['project_id'=>$params['project_id']]);
        if ($project_station_res['code'] != 0) {
            rsp_error_tips('10002', '项目车场信息查询失败');
        }
        if (!empty($res['content'])) {
            foreach ($res['content']['sub_tnums'] as $kl => $vl) {
                $sub_orders[$vl['tnum']]['platform_type_tag_id'] = $project_station_res['content']['platform_type'] ?? '';
                $sub_orders[$vl['tnum']]['toll_system_tag_id'] = $project_res['content']['toll_system_tag_id'] ?? '';
                $sub_orders[$vl['tnum']]['out_trade_tnum'] = $vl['out_trade_tnum'];
                $sub_orders[$vl['tnum']]['business_tnum'] = $res['content']['tnum'];
                $sub_orders[$vl['tnum']]['channel_tag_id'] = $res['content']['config_used']['source_tag_id'];
                $sub_orders[$vl['tnum']]['charge_source_tag_id'] = '1132';
                $sub_orders[$vl['tnum']]['jsfrom_id'] = $_SESSION['jsfrom_id'] ?? '';
                $sub_orders[$vl['tnum']]['trade_type_tag_id'] = $params['trade_type_tag_id'];
                $sub_orders[$vl['tnum']]['order_status_tag_id'] = self::PAY_STATUS['未支付'];
                $sub_orders[$vl['tnum']]['house_id'] = $params['house_id'] ?? '';
                $sub_orders[$vl['tnum']]['project_id'] = $params['project_id'] ?? '';
                $sub_orders[$vl['tnum']]['space_id'] = $params['space_id'] ?? '';
                $sub_orders[$vl['tnum']]['cell_id'] = $params['cell_id'] ?? '';
                $sub_orders[$vl['tnum']]['remark'] = $params['remark'] ?? '';
                $sub_orders[$vl['tnum']]['create_client_id'] = $params['create_client_id'] ?? "";
                $sub_orders[$vl['tnum']]['sender_client_id'] = $this->sender_client_id ?? '';
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
            rsp_error_tips($result['code'], $result['message']);
        }

        $es_result = Comm_EventTrigger::push('gene_es_order',['data'=>$sub_orders,'app_id'=>$_SESSION['oauth_app_id']]);
        if (empty($es_result)){
            rsp_die_json(10002, $es_result['message']);
        }
        if(!empty($params['qr_num'])){
            $key = "etbase:wechat_qr:order_num:".$params['qr_num'];
            $redis = Comm_Redis::getInstance();
            $redis->select(8);
            $redis->setex($key, 180, $res['content']['tnum']);
        }
        rsp_success_json($tnum);
    }

    private function getSign($params = [], $pri_key = '')
    {
        ksort($params);
        $str = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        openssl_sign($str, $sign, $pri_key);
        return base64_encode($sign);
    }
}