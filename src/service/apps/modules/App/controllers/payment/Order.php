<?php

final class Order extends Base
{
    public function lists($params = [])
    {
        if (!isTrueKey($params,...['tabletime'])) rsp_error_tips(10001, 'tabletime');
        $where = [
            'tabletime' => $params['tabletime'],
            'status_tag_id' => self::STATUS['已支付'],
        ];
        if (isTrueKey($params, 'page')) $where['page'] = $params['page'];
        if (isTrueKey($params, 'pagesize')) $where['pagesize'] = $params['pagesize'];

        if(!empty($_SESSION['user_id']) && empty($_SESSION['access_control_source_id'])){
            $client_lists = $this->user->post('/client/lists',['user_id'=>$_SESSION['user_id']]);
            if($client_lists['code']!=0 || ($client_lists['code']==0 && empty($client_lists['content']))){
                rsp_die_json(10002,"客户端查询失败");
            }
            log_message('----app.order.lists----'.json_encode($client_lists));
            $where['client_ids'] = array_column($client_lists['content'],'client_id');
        }else if(empty($_SESSION['user_id']) && empty($_SESSION['access_control_source_id'])){
            $where['client_ids'] = [$this->client_id];
        }

        log_message('----app.order.lists----'.json_encode($_SESSION));
        if(!empty($_SESSION['employee_id']) && !empty($_SESSION['access_control_source_id'])){
            $client_lists = $this->user->post('/client/lists',['employee_id'=>$_SESSION['employee_id']]);
            if($client_lists['code']!=0 || ($client_lists['code']==0 && empty($client_lists['content']))){
                rsp_die_json(10002,"客户端查询失败");
            }
            $where['created_bys'] = array_column($client_lists['content'],'client_id');
        }else if(empty($_SESSION['employee_id']) && !empty($_SESSION['access_control_source_id'])){
            $where['created_bys'] = [$this->client_id];
        }
        $res = Comm_Pay::gateway('app.order.lists', $where);
        $res = ($res['code'] === 0 && $res['content']) ? $res['content'] : [];
        if (!$res) rsp_success_json(['total'=>0,'lists'=>[],'sum'=>0]);
        rsp_success_json($res);
    }

    public function show($params = [])
    {
        if (!isTrueKey($params,...['tnum'])) rsp_error_tips(10001, 'tnum');
        $where = [
            'client_id' => $this->client_id,
            'tnum' => $params['tnum'],
        ];
        $res = Comm_Pay::gateway('app.order.show', $where);
        $res = ($res['code'] === 0 && $res['content']) ? $res['content'] : [];
        if (!$res) rsp_success_json([]);
        rsp_success_json($res);
    }

    /**
     * @param array $params订单查找接口
     */
    public function posLists($params = [])
    {
        $post = [];
        if ( empty($_SESSION['employee_id']) && empty($_SESSION['user_id'])){
            rsp_die_json(10002,"参数缺失");
        }
        if (!empty($params['order_status_tag_id'])){
            $post['order_status_tag_id'] =$params['order_status_tag_id'];
        }
        if (!empty($params['business_tnum'])){
            $post['business_tnum'] =$params['business_tnum'];
        }
        if (!empty($params['tabletime'])){
            $date = str_split($params['tabletime'],4);
            $post['time_begin'] = date('Y-m-d 00:00:00',strtotime("first day of ".implode("-",$date)));
            $post['time_end'] = date('Y-m-d 23:59:59',strtotime("last day of ".implode("-",$date)));
            unset($post['tabletime']);
            $post['postfix'] = $params['tabletime'];
        }
        if(!empty($_SESSION['user_id']) && empty($_SESSION['access_control_source_id'])){
            $client_lists = $this->user->post('/client/lists',['user_id'=>$_SESSION['user_id']]);
            if($client_lists['code']!=0 || ($client_lists['code']==0 && empty($client_lists['content']))){
                rsp_die_json(10002,"客户端查询失败");
            }
            $post['all_client_ids'] = array_column($client_lists['content'],'client_id');
            if (!empty($_SESSION['client_id'])){
                $post['or_sender_client_id'] = $_SESSION['client_id'];
            }
        }else if(empty($_SESSION['user_id']) && empty($_SESSION['access_control_source_id'])){
            $post['all_client_ids'] = [$post['client_id']];
            if (!empty($_SESSION['client_id'])){
                $post['or_sender_client_id'] = $_SESSION['client_id'];
            }
        }

        if(!empty($_SESSION['employee_id']) && !empty($_SESSION['access_control_source_id'])){
            $client_lists = $this->user->post('/client/lists',['employee_id'=>$_SESSION['employee_id']]);
            if($client_lists['code']!=0 || ($client_lists['code']==0 && empty($client_lists['content']))){
                rsp_die_json(10002,"客户端查询失败");
            }
            $post['create_client_ids'] = array_column($client_lists['content'],'client_id');
        }else if(empty($_SESSION['employee_id']) && !empty($_SESSION['access_control_source_id'])){
            $post['create_client_ids'] = [$post['client_id']];
        }
        $post['page'] = $params['page']??'0';
        $post['pagesize'] = $params['pagesize']??'20';
        if(empty($post['create_client_ids'])&&empty($post['all_client_ids'])){
            rsp_die_json(10002,"客户端查询失败");
        }
        $result = $this->order->post('/order/lists',$post);
        $result = ($result['code'] === 0 && $result['content']) ? $result['content'] : [];
        if (!$result){
            rsp_success_json(["lists"=>[],'count'=>0]);
        }
        unset($post['page']);
        unset($post['pagesize']);
        $count = $this->order->post('/order/count',$post);
        if ($count['code']!=0){
            rsp_die_json(10002,$count['message']);
        }
        $sum = $this->order->post('/order/sum',$post);
        if ($sum['code']!=0){
            rsp_die_json(10002,$count['message']);
        }
        foreach($result as $k=>$v){
            $result[$k]['paid_time'] = !empty($v['paid_time']) ? date('Y-m-d H:i:s',$v['paid_time']):"";
        }

        rsp_success_json(["lists"=>$result,'count'=>$count['content'],'total_amount'=>$sum['content']]);
    }

    public function posOrderSublists($params = [])
    {
        $post = [];
        if (!empty($params['client_id'])){
            if(empty($_SESSION['user_id'])){
                rsp_die_json(10002,"未绑定手机号");
            }
            $client_lists = $this->user->post('/client/lists',['user_id'=>$_SESSION['user_id']]);
            if($client_lists['code']!=0 || ($client_lists['code']==0 && empty($client_lists['content']))){
                rsp_die_json(10002,"客户端查询失败");
            }
            $post['all_client_ids'] = array_column($client_lists['content'],'client_id');
        }
        if (!empty($params['order_status_tag_id'])){
            $post['order_status_tag_id'] =$params['order_status_tag_id'];
        }
        if (isTrueKey($params, 'order_id')){
            $post['order_id'] = $params['order_id'];
        }

        if (isTrueKey($params, 'tnum')){
            $post['tnum'] = $params['tnum'];
        }
        if (isTrueKey($params, 'business_tnum')){
            $post['business_tnum'] = $params['business_tnum'];
        }
        if (isTrueKey($params, 'out_trade_tnum')){
            $post['out_trade_tnum'] = $params['out_trade_tnum'];
        }
        $post['page'] = $params['page']??'0';
        $post['pagesize'] = $params['pagesize']??'20';
        $result = $this->order->post('/order/lists',$post);
        $result = ($result['code'] === 0 && $result['content']) ? $result['content'] : [];
        if (!$result) rsp_success_json([]);
        unset($post['page']);
        unset($post['pagesize']);
        $tnum_arrs = array_column($result,'tnum');
        $subOrder = $this->order->post('/suborder/lists',['tnums' => $tnum_arrs]);
        $subOrder = ($subOrder['code'] === 0 && $subOrder['content']) ? $subOrder['content'] : [];
        $result = array_column($result,null,'tnum');
        $create_client_arr = array_column($result,'create_client_id');
        $client_arr = array_column($result,'client_id');
        $client_id_arrs = array_unique(array_merge($create_client_arr,$client_arr));
        $client_res = $this->user->post('/client/lists',['client_ids'=>$client_id_arrs]);
        $client_id_lists = ($client_res['code'] === 0 && $client_res['content']) ? $client_res['content'] : [];
        $client_id_lists = array_column($client_id_lists,null,'client_id');
        foreach ($result as $kl=>$vl){
            $result[$kl]['paid_time'] =  !empty($result[$kl]['paid_time']) ? date('Y-m-d H:i:s',$result[$kl]['paid_time']):"";
            $result[$kl]['user_id'] = !empty($client_id_lists[$vl['client_id']])?$client_id_lists[$vl['client_id']]['user_id']:0;
            $result[$kl]['create_user_id'] = !empty($client_id_lists[$vl['client_id']])?$client_id_lists[$vl['create_client_id']]['user_id']:0;

            $result[$kl]['employee_id'] = !empty($client_id_lists[$vl['client_id']])?$client_id_lists[$vl['client_id']]['employee_id']:0;
            $result[$kl]['create_employee_id'] = !empty($client_id_lists[$vl['client_id']])?$client_id_lists[$vl['create_client_id']]['employee_id']:0;
        }
        foreach($subOrder as $k =>$v){
            if(!empty($result[$v['tnum']]['subs'][$v['year'].".".$v['tnum_month']]['total_amount'])){
                $result[$v['tnum']]['subs'][$v['year'].".".$v['tnum_month']]['total_amount'] += $v['total_amount'];
            }else{
                $result[$v['tnum']]['subs'][$v['year'].".".$v['tnum_month']]['total_amount'] = $v['total_amount'];
            }
            if(!empty($result[$v['tnum']]['subs'][$v['year'].".".$v['tnum_month']]['amount'])){
                $result[$v['tnum']]['subs'][$v['year'].".".$v['tnum_month']]['amount'] += $v['amount'];
            }else{
                $result[$v['tnum']]['subs'][$v['year'].".".$v['tnum_month']]['amount'] = $v['amount'];
            }
            $result[$v['tnum']]['subs'][$v['year'].".".$v['tnum_month']]['tnum_month'] = $v['year'].".".$v['tnum_month'];
            $v['paid_time'] = !empty( $v['paid_time'])? date('Y-m-d H:i:s',$v['paid_time']):'';
            $result[$v['tnum']]['subs'][$v['year'].".".$v['tnum_month']]['subs'][] = $v;
        }
        rsp_success_json($result);
    }

    /**
     * @param array $params
     * 订单展示
     */
    public function posOrderSubshow($params = [])
    {
        if (!isTrueKey($params, 'order_id') && !isTrueKey($params, 'tnum') &&
            !isTrueKey($params, 'business_tnum')&& !isTrueKey($params, 'out_trade_tnum')) {
            rsp_die_json(10002,"参数错误");
        }
        $post = [];
        if (isTrueKey($params, 'order_id')){
            $post['order_id'] = $params['order_id'];
        }
        if (isTrueKey($params, 'tnum')){
            $post['tnum'] = $params['tnum'];
        }
        if (isTrueKey($params, 'business_tnum')){
            $post['business_tnum'] = $params['business_tnum'];
        }
        if (isTrueKey($params, 'out_trade_tnum')){
            $post['out_trade_tnum'] = $params['out_trade_tnum'];
        }
        $result = $this->order->post('/order/show',$post);
        $result = ($result['code'] === 0 && $result['content']) ? $result['content'] : [];
        if (!$result) rsp_success_json([]);
        $subOrder = $this->order->post('/suborder/lists',['tnum' => $result['tnum']]);
        if($subOrder['code'] === 0 && empty($subOrder['content'])){
            $subOrder['content'] = [];
        }else{
            foreach ($subOrder['content'] as $kv=>$vv){
                if(!empty($result['subs'][$vv['year'].".".$vv['tnum_month']]['total_amount'])){
                    $result['subs'][$vv['year'].".".$vv['tnum_month']]['total_amount'] += $vv['total_amount'];
                }else{
                    $result['subs'][$vv['year'].".".$vv['tnum_month']]['total_amount'] = $vv['total_amount'];
                }
                if(!empty($result['subs'][$vv['year'].".".$vv['tnum_month']]['amount'])){
                    $result['subs'][$vv['year'].".".$vv['tnum_month']]['amount'] += $vv['amount'];
                }else{
                    $result['subs'][$vv['year'].".".$vv['tnum_month']]['amount'] = $vv['amount'];
                }
                $vv['paid_time'] = !empty( $vv['paid_time'])? date('Y-m-d H:i:s',$vv['paid_time']):'';
                $result['subs'][$vv['year'].".".$vv['tnum_month']]['tnum_month'] = $vv['year'].".".$vv['tnum_month'];
                $result['subs'][$vv['year'].".".$vv['tnum_month']]['subs'][] = $vv;
            }
        }
        $result['paid_time'] = !empty($result['paid_time']) ? date('Y-m-d H:i:s',$result['paid_time']):"";
        rsp_success_json($result);
    }

    /**
     * @param array $params
     * 订单展示
     */
    public function posShow($params = [])
    {
        if (!isTrueKey($params, 'order_id') && !isTrueKey($params, 'tnum') &&
            !isTrueKey($params, 'business_tnum')&& !isTrueKey($params, 'out_trade_tnum')) {
            rsp_die_json(10002,"参数错误");
        }
        $post = [];
        if (isTrueKey($params, 'order_id')){
            $post['order_id'] = $params['order_id'];
        }
        if (isTrueKey($params, 'tnum')){
            $post['tnum'] = $params['tnum'];
        }
        if (isTrueKey($params, 'business_tnum')){
            $post['business_tnum'] = $params['business_tnum'];
        }
        if (isTrueKey($params, 'out_trade_tnum')){
            $post['out_trade_tnum'] = $params['out_trade_tnum'];
        }
        $result = $this->order->post('/order/show',$post);
        $result = ($result['code'] === 0 && $result['content']) ? $result['content'] : [];
        if (!$result) rsp_success_json([]);
        $result['paid_time'] = !empty($result['paid_time']) ? date('Y-m-d H:i:s',$result['paid_time']):"";
        rsp_success_json($result);
    }

    public function posSubLists($params){
        if (!isTrueKey($params,...['tnum'])) rsp_error_tips(10001, 'tnum');
        $subOrder = $this->order->post('/suborder/lists',['tnum' => $params['tnum']]);
        $subOrder = ($subOrder['code'] === 0 && $subOrder['content']) ? $subOrder['content'] : [];
        if (!$subOrder) rsp_success_json([]);
        foreach($subOrder as $k=>$v){
            $subOrder[$k]['paid_time'] = !empty($v['paid_time']) ? date('Y-m-d H:i:s',$v['paid_time']):"";
        }
        rsp_success_json($subOrder);
    }

    public function tradeSourceLists()
    {
        $result = $this->order->post('/tradesource/lists',['page'=>0,'pagesize'=>0]);
        if (!$result) rsp_success_json([]);
        rsp_success_json($result['content']);
    }

    public function geneOrderNum($params = []){
        if (!isTrueKey($params,...['trade_source_id'])){
            rsp_error_tips(10001, 'trade_source_id不能为空');
        }
        $tnum =OrderModel::new_order_sn();
        $tnum = str_pad($params['trade_source_id'],4,"0",STR_PAD_RIGHT).$tnum;
        rsp_success_json($tnum);
    }


    public function getQrCode($params = [])
    {
        $num = OrderModel::new_order_sn();
        if(empty($num)){
            rsp_die_json(10002,"二维码code生成失败");
        }
        $key = "etbase:wechat_qr:qr_num:".$num;
        log_message('order_info3:' . json_encode($key));
        log_message('order_info4:' . json_encode($params));
        $redis = Comm_Redis::getInstance();
        $redis->select(8);
        $redis->setex($key,180,json_encode($params,true));
        rsp_success_json("{$num}");
    }

    public function getQrInfo($params = [])
    {
        if (!isTrueKey($params, ...['qr_num'])) {
            rsp_die_json(10002,"二维码id不存在");
        }
        $key = "etbase:wechat_qr:qr_num:".$params['qr_num'];
        log_message('order_info1:' . json_encode($key));
        $redis = Comm_Redis::getInstance();
        $redis->select(8);
        $result = $redis->get($key);
        log_message('order_info2:' . json_encode($result));
        $redis->del($key);
        if(!empty($params['qr_num'])){
            $key = "etbase:wechat_qr:order_num:".$params['qr_num'];
            $redis->setex($key,180,"12345");
        }
        if(empty($result)){
            rsp_die_json(10002,"二维码已失效");
        }
        rsp_success_json(json_decode($result));
    }

    public function getQrStatus($params = [])
    {
        if (!isTrueKey($params, ...['qr_num'])) {
            rsp_die_json(10002,"二维码id不存在");
        }
        $key = "etbase:wechat_qr:qr_num:".$params['qr_num'];
        $redis = Comm_Redis::getInstance();
        $redis->select(8);
        $result = $redis->get($key);
        if(empty($result)){
            rsp_die_json(10002,"二维码已失效");
        }
        rsp_success_json('');
    }

    public function updateOrderStatus($params = [])
    {
        if (!isTrueKey($params, ...['business_tnum'])) {
            rsp_die_json(10002,"二维码id不存在");
        }
        $order_res = $this->order->post('/order/show', ['business_tnum'=>$params['business_tnum']]);
        if($order_res['code']!=0 ||  ($order_res['code']==0 && empty($order_res['content']))){
            rsp_die_json(10002,"订单查询失败");
        }
        if((int)$order_res['content']['order_status_tag_id'] == self::PAY_STATUS['已支付']) {
            rsp_die_json(10002,"已支付订单无法修改");
        }
        $order_update_res = $this->order->post('/order/update', ['tnum'=>$order_res['content']['tnum'],
            'order_status_tag_id'=>self::PAY_STATUS['已取消'],'paid_time'=>time()]);
        if($order_update_res['code']!=0){
            rsp_die_json(10002,"订单修改失败");
        }
        $sub_order_update_res = $this->order->post('/suborder/update', ['tnum'=>$order_res['content']['tnum'],
            'order_status_tag_id'=>self::PAY_STATUS['已取消'],'paid_time'=>time()]);
        if($sub_order_update_res['code']!=0){
            rsp_die_json(10002,"子订单修改失败");
        }
        rsp_success_json('','订单状态修改成功');
    }

    public function getOrderStatus($params = [])
    {
        if (!isTrueKey($params, ...['qr_num'])) {
            rsp_die_json(10002,"二维码id不存在");
        }
        $key = "etbase:wechat_qr:order_num:".$params['qr_num'];
        $redis = Comm_Redis::getInstance();
        $redis->select(8);
        $tnum = $redis->get($key);
        if(empty($tnum)){
            rsp_success_json(['status'=>"OVERDUE"]);
        }
        $result = $this->order->post('/order/show',['business_tnum'=>$tnum]);
        if($result['code'] ==0 && !empty($result['content'])){
            if( $result['content']['order_status_tag_id'] ==  self::PAY_STATUS['已支付'] ){
                rsp_success_json(['status'=>'SUCCESS','business_tnum'=>$tnum]);
            }
            if( $result['content']['order_status_tag_id'] ==  self::PAY_STATUS['支付失败']
                || $result['content']['order_status_tag_id'] ==  self::PAY_STATUS['已取消']){
                rsp_success_json(['status'=>"FAIL",'business_tnum'=>$tnum]);
            }
        }
        rsp_success_json(['status'=>"PAYING"]);
    }

    public function printOrder($params=[]){
        $check_params_info = checkEmptyParams($params, ['tnum','order_type_name',
            'device_sn']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $result = $this->order->post('/order/show',['tnum'=>$params['tnum']]);
        $result = ($result['code'] === 0 && $result['content']) ? $result['content'] : [];
        if (!$result) {
            rsp_die_json(10002,'订单查找失败');
        }
        $params['tnum'] = $params['tnum'];
        $params['business_tnum'] = $result['business_tnum'];
        $project_res = $this->pm->post('/project/lists',['project_ids'=>[$result['project_id']]]);
        if ($project_res['code'] !== 0){
            rsp_die_json(10002,$project_res['message']);
        }
        if (empty($project_res['content'])){
            rsp_die_json(10002,'项目不存在');
        }
        $project_res = $this->pm->post('/project/lists',['project_ids'=>[$result['project_id']]]);
        if ($project_res['code'] !== 0){
            rsp_die_json(10002,$project_res['message']);
        }
        if (empty($project_res['content'])){
            rsp_die_json(10002,'项目不存在');
        }

        $company_res = $this->company->post('/company/lists', ['project_id'=>[$result['project_id']]]);
        if ($company_res['code'] !== 0){
            rsp_die_json(10002,$company_res['message']);
        }
        if (empty($company_res['content'])){
            rsp_die_json(10002,'公司不存在');
        }
        $params['company_name'] = $company_res['content'][0]['company_name'];
        if(!empty($result['client_id'])){
            $client_res = $this->user->post('/client/show',['client_id'=>$result['client_id']]);
            if ($client_res['code'] !== 0){
                rsp_die_json(10002,$client_res['message']);
            }
            if (empty($client_res['content']['user_id'])){
                $mobile = ' ';
            }else{
                $user_res = $this->user->post('/user/show',['user_id'=>$client_res['content']['user_id']]);
                if ($user_res['code'] !== 0){
                    rsp_die_json(10002,$user_res['message']);
                }
                $mobile = $user_res['content']['mobile'];
            }
        }else{
            $mobile = ' ';
        }
        $params['mobile'] = $mobile;
        $attach = json_decode($result['attach'],true);
        $params['address'] = $attach['address']??'';
        $config = getConfig('ms.ini');
        $params['qrcode_image_url'] = $config->get('yhywy_img.url');
        $subOrder = $this->order->post('/suborder/lists',['tnum' => $result['tnum']]);
        if($subOrder['code'] === 0 && empty($subOrder['content'])){
            $subOrder['content'] = [];
        }else{
            foreach ($subOrder['content'] as $kv=>$vv){
                if(!empty($params['detail'][$vv['year'].".".$vv['tnum_month']]['amount'])){
                    $params['detail'][$vv['year'].".".$vv['tnum_month']]['amount'] += $vv['amount']/100;
                }else{
                    $params['detail'][$vv['year'].".".$vv['tnum_month']]['amount'] = $vv['amount']/100;
                }
                $params['detail'][$vv['year'].".".$vv['tnum_month']]['date'] = $vv['year']."年".$vv['tnum_month']."月";
            }
        }
        $res = Comm_Pay::gateway('app.order.print', $params);
        if ($res['code'] != 0) {
            rsp_error_tips($res['code'], $res['message']);
        }
        if (empty($res)) {
            rsp_die_json(10001, '打印小票异常');
        }
        rsp_success_json([],'打印成功');
    }

    public function orderBind($params = [])
    {
        $check_params_info = checkEmptyParams($params, ['tnum']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        $order_res = $this->order->post('/order/show',['tnum'=>$params['tnum']]);
        if ($order_res['code'] != 0 || ($order_res['code']==0 && empty($order_res['content']))) {
            rsp_die_json(10001, '订单号查询失败');
        }
        if (!empty($order_res['content']['client_id'])) {
            rsp_die_json(10001, '该订单已被绑定');
        }
        $bind_Params = ['tnum'=>$order_res['content']['business_tnum'],'client_id'=>$_SESSION['client_id']];
        $res = Comm_Pay::gateway('app.order.bind', $bind_Params);
        if ($res['code'] != 0) {
            rsp_error_tips($res['code'], $res['message']);
        }
        $order_bind_Params = ['tnum'=>$params['tnum'],'client_id'=>$_SESSION['client_id'],
            'sender_client_id' => $_SESSION['client_id']];
        $result = $this->order->post('/order/update',$order_bind_Params);
        if ($result['code'] != 0) {
            rsp_die_json(10001, $result['message']);
        }
        if(!empty($this->user_id)){
            rsp_success_json('');
        }
        $client_res = $this->user->post('/client/show',['client_id'=>$_SESSION['client_id']]);
        if(0 != $client_res['code'] && (0  == $client_res['code'] && !empty($client_res['content']['user_id']))){
            rsp_die_json(10001, '查询失败 ');
        }
        $rps = $this->user->post('/client/update',['client_id'=>$_SESSION['client_id'],'user_id'=>$client_res['content']['user_id']]);
        if(0 != $rps['code']){
            rsp_die_json(10001,'绑定失败');
        }
        rsp_success_json('');
    }

    public function userBind($params = [])
    {
        $check_params_info = checkEmptyParams($params, ['business_tnum']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if(empty($_SESSION['client_id'])){
            rsp_die_json(10002,'登录失效');
        }
        $order_update_res = $this->order->post('/order/update',['business_tnum'=>$params['business_tnum'],'sender_client_id'=>$_SESSION['client_id']]);
        log_message('----orderUpdate----'.json_encode($order_update_res));
        if ($order_update_res['code'] != 0 ) {
            rsp_die_json(10001, '订单修改失败');
        }
        if(!empty($this->user_id)){
            rsp_die_json(10002,'绑定成功');
        }
        $order_res = $this->order->post('/order/show',['business_tnum'=>$params['business_tnum']]);
        log_message('----orderUpdate----'.json_encode($order_res));
        if ($order_res['code'] != 0 || ($order_res['code']==0 && empty($order_res['content']))) {
            rsp_die_json(10001, '订单号查询失败');
        }
        if (empty($order_res['content']['client_id'])) {
            rsp_die_json(10001, '该订单未被绑定');
        }
        $client_res = $this->user->post('/client/show',['client_id'=>$order_res['content']['client_id']]);
        if(0 != $client_res['code'] && (0  == $client_res['code'] && !empty($client_res['content']['user_id']))){
            rsp_die_json(10001, '客户端查询失败 ');
        }
        $rps = $this->user->post('/client/update',['client_id'=>$_SESSION['client_id'],'user_id'=>$client_res['content']['user_id']]);
        if(0 != $rps['code']){
            rsp_die_json(10001,'客户端绑定失败');
        }
        rsp_success_json('','绑定成功');
    }
}