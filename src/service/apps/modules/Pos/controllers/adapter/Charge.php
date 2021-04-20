<?php

include __DIR__ . '/Basecharging.php';

class Charge extends Basecharging {

    public function detail($params = [])
    {
        log_message(__METHOD__ . '------' . json_encode($params));
        $result = (new Basecharging())->getChargeLists($params);
        rsp_success_json($result,'请求成功');
    }

	public function detail_limit($params = []){
		log_message(__METHOD__.'------'.json_encode($params) );
		if(isTrueKey($params,'openid','client_id') == false) rsp_die_json(10001,'参数缺失');
		$config = getConfig('other.ini');
		$time = $config->get('charge.detail_see_time_limit') ?: 600;
		$times_limit = $config->get('charge.detail_times_limit') ?: 5;

		$redis = Comm_Redis::getInstance();
		$redis->select(8);
		$key = 'ADAPTER_'.$params['client_id'].'_'.$params['openid'];
		$redis->INCRBY($key,1);
		$redis->expire($key,$time);

		$count = $redis->get($key);
		if($count > $times_limit) rsp_die_json(10002,'您无权限查看费用详情，请进行业主认证');
		rsp_success_json('','success');
	}

	public function print_receipt($params = []){
        try {
            log_message(__METHOD__ . '----' . json_encode($params));
            if (isTrueKey($params, 'tnum', 'paidtime') == false) rsp_die_json(10001, '参数缺失');
            $receipt_show = $this->adapter->post('/bill/receipt/show', ['tnum' => $params['tnum']]);
            //文件服务对象
            $file_object = new \Receipt\FileModel();
            if ($receipt_show['code'] == 0 && !empty($receipt_show['content'])) {
                //文件读取
                $info = $file_object->read($receipt_show['content']['file_id']);
                $image = $file_object->download($info['url']);
                $base64_file = chunk_split(base64_encode($image));
                rsp_success_json(['file_addr'=>$base64_file ],'success');
            }
            $table_time = date('Ym', $params['paidtime']);
            $order_rsp = curl_json('POST', $this->etam_url . '/sub/order/show', ['p_tnum' => $params['tnum'], 'tabletime' => $table_time]);
            if ($order_rsp['code'] != 0) rsp_die_json(10002, '订单信息不存在');
            $attach = json_decode($order_rsp['content']['attach'], true);
            if(isTrueKey($attach,'house_room','space_name','project_id') === false ) rsp_die_json(10002,'订单附加数据有误');
            if(!isset($attach['collect_penalty']) ) rsp_die_json(10002,'订单附加数据有误2');
            //查询签章信息
            $signature_show = $this->pm->post('/signature/show', [
                'project_id' => $attach['project_id'],
                'signature_type' => 'R',
                'status' =>'Y',
            ]);
            if ($signature_show['code'] != 0 || empty($signature_show['content'])) rsp_die_json(10003, '项目签章信息不存在');
            //查询客户管理信息
            $company_show = $this->company->post('/corporate/lists',[
               'company_ids'=>[$signature_show['content']['property_company_id']]
            ]);
            if ($company_show['code'] != 0 || empty($company_show['content'])) rsp_die_json(10003, '物业公司信息不存在');
            $signature_show['content']['company_name'] = $company_show['content'][0]['company_name'];
            $signature_show['content']['collect_penalty'] = $attach['collect_penalty'];
            //查询楼栋(空间)信息
            $space_show = $this->pm->post('/space/show', [
                'project_id' => $attach['project_id'],
                'space_name' => $attach['space_name'],
            ]);
            if ($space_show['code'] != 0 || empty($space_show['content'])) rsp_die_json(10003, '楼栋信息不存在');
            //查询产权人信息
            $house_show = $this->pm->post('/house/property/detail', [
                'project_id' => $attach['project_id'],
                'space_id' => trim($space_show['content']['space_id']),
                'full_house_room' =>trim($attach['house_room']),
            ]);
            if ($house_show['code'] != 0 || empty($house_show['content'])) rsp_die_json(10003, '房产信息不存在');
            $owner = [];
            foreach ($house_show['content'] as $item){
                foreach ($item['house_property'] as $key=>$value){
                    if($value['proprietor_type'] == 'owner'){
                        $owner = $value;
                    }
                }
            }
            //查询（旧平台）订单信息
            $bill_show = $this->adapter->post('/bill/one', [
                'project_id' => $attach['project_id'],
                'house_room' => trim($attach['house_room']),
                'paidtime' => $params['paidtime'],
            ]);
            if ($bill_show['code'] != 0) rsp_die_json(10003, $bill_show['message']);
            $bill_detail = $this->adapter->post('/bill/detail', ['id' => $bill_show['content']['csmId']]);
            if ($bill_detail['code'] != 0) rsp_die_json(10003, $bill_detail['message']);
            $file_path = DATA_PATH . '/temp/' . md5(time()) . '.pdf';
            $data = [
                'file_path' => $file_path,
                'data' => $bill_detail['content']
            ];
            //生成电子收据临时文件
            $receipt_obj = new \Receipt\ReceiptModel($params['tnum'],$data,$params['paidtime'],$signature_show['content'],$owner);
            $receipt_md5 = $receipt_obj->get_params();
            //文件上传
            $file_id = $file_object->upload('receipt', $file_path);
            //文件读取
            $info = $file_object->read($file_id);
            unlink($file_path);
            //订单号与文件id映射关系添加
            $this->adapter->post('/bill/receipt/add', [
                'tnum' => $params['tnum'],
                'file_id' => $file_id,
                'receipt_md5' => $receipt_md5,
            ]);
            $image = $file_object->download($info['url']);
            $base64_file = chunk_split(base64_encode($image));
            rsp_success_json(['file_addr'=>$base64_file],'success');
        }catch(\Exception $e){
            log_message('打印收据异常----MSG='.$e->getMessage().'---订单号:'.$params['tnum']);
            rsp_die_json('10004','电子收据查看失败，请稍后重试');
        }
    }

    /**
     * 是否支持收据打印
     * @param array $params
     */
    public function support_receipt($params = []){
	    log_message(__METHOD__ . '----' . json_encode($params));
	    if (isTrueKey($params, 'tnum', 'paidtime') == false) rsp_die_json(10001, '参数缺失');
        $table_time = date('Ym', $params['paidtime']);
        $order_rsp = curl_json('POST', $this->etam_url . '/sub/order/show', ['p_tnum' => $params['tnum'], 'tabletime' => $table_time]);
        if ($order_rsp['code'] != 0) rsp_die_json(10002, '订单信息不存在');
        $attach = json_decode($order_rsp['content']['attach'], true);
        log_message('-xxxx---'.json_encode($attach));
        if(isTrueKey($attach,'house_room','space_name','project_id') === false ) rsp_die_json(10002,'订单附加数据有误');
        if(!isset($attach['collect_penalty']) ) rsp_die_json(10002,'订单附加数据有误2');
        //查询项目信息
		$project_show = $this->pm->post('/project/show',['project_id'=>$attach['project_id'] ]);
		if($project_show['code'] != 0 || !$project_show['content']) rsp_die_json(10002,'项目信息不存在');
		rsp_success_json($project_show['content'],'success');
    }

    /**
     * 提供给测试，查询欠费的房子
     * @param array $params
     */
    public function arrears_test($params = []){
        log_message(__METHOD__ . '----' . json_encode($params));
        if(isTrueKey($params,'project_id','space_name') == false) rsp_die_json(10001,'参数缺失');
        $data = [
          'project_id'=>$params['project_id'],
          'space_name'=>$params['space_name'],
        ];
        if(isTrueKey($params,'house_room')) $data['house_room'] = $params['house_room'];
        $result = $this->adapter->post('/bill/arrears/customers', $data);
        if(!$result || (int)$result['code'] != 0) rsp_die_json(10002,'查询失败');
        rsp_success_json($result['content'],'查询成功');
    }

    /**
     * 销单
     * @param array $params
     */
    public function cancel_order($params = []){
        log_message(__METHOD__ . '----' . json_encode($params));
	    if (isTrueKey($params, 'tnum') == false) rsp_die_json(10001, '参数缺失');
        $time = rSnowFlake($params['tnum']);
	    $table_time = date('Ym', $time );
        //查询主订单
        $order_rsp = curl_json('POST',$this->etam_url.'/order/show',['tnum'=>$params['tnum'],'tabletime'=>$table_time]);
        if(!$order_rsp || $order_rsp['code'] != 0 || empty($order_rsp['content']) ) rsp_die_json(10002,'主订单信息不存在');
        if((int)$order_rsp['content']['status_tag_id'] !== 586) rsp_die_json(10002,'该笔订单未完成支付');
	    //查询子订单
	    $sub_order_rsp = curl_json('POST', $this->etam_url . '/sub/order/show', ['p_tnum' => $params['tnum'], 'tabletime' =>$table_time]);
        if ($sub_order_rsp['code'] != 0 || empty($sub_order_rsp['content'])) rsp_die_json(10002, '订单信息不存在');
        $attach = json_decode($sub_order_rsp['content']['attach'], true);
        log_message('-xxxx111---'.json_encode($attach));

        //查询下该房屋还在欠费
        $attach['charge_date'] = date('Y.m',$time);
        $charge_info = $this->adapter->post('/charge/detail',$attach);
        if(!$charge_info || $charge_info['code'] != 0) {
            $msg = isset($charge_info['message']) ? $charge_info['message'] : '';
            rsp_die_json(10002,'查费接口异常:'.$msg);
        }
        if($charge_info['content']['charge_total'] == 0 || empty($charge_info['content']['charge_detail'])){
            rsp_success_json('','销单成功1');
        }
        $channel = $order_rsp['content']['source_tag_id'] == '670' ? 'WECHAT' : 'QWXPAY';
        $notice_params = [
            'tnum'=>$params['tnum'],
            'mch_id'=>1,
            'channel'=>$channel,
            'paidtime'=>$time,
            'total_amount'=>$order_rsp['content']['total_amount'],
            'amount'=>$order_rsp['content']['amount'],
            'attach'=>json_encode($attach),
            'status'=>'SUCCESS',
        ];
        $notice_rsp = $this->adapter->post('/pay/notice',json_encode($notice_params),['content-type:application/json']);
        if(!$notice_rsp || $notice_rsp['status'] != 'SUCCESS') rsp_die_json(10005,'销单失败');
        rsp_success_json('','销单成功2');
    }
}


