<?php

final class Order extends Base
{
    public function posLists($params = [])
    {
        unsetEmptyParams($params);
        $post = $params;
        if (!empty($params['order_status_tag_id'])){
            $post['order_status_tag_id'] =$params['order_status_tag_id'];
        }
        //支付时间搜索
        $post['postfix'] = '';
        if (!empty($params['paid_time_begin']) || !empty($params['paid_time_end'])){
            if(empty($params['paid_time_begin'])){
                rsp_die_json(10002,"支付开始时间不能为空");
            }
            if(empty($params['paid_time_end'])){
                rsp_die_json(10002,"支付结束时间不能为空");
            }
            $paid_month_begin = date('Ym', strtotime($params['paid_time_begin']));
            $post['paid_time_begin'] = $params['paid_time_begin'];
            $post['paid_time_end'] = $params['paid_time_end'];
            $post['postfix'] = $paid_month_begin;
        }
        //创建时间搜索
        if (!empty($params['create_time_begin']) || !empty($params['create_time_end'])){
            if(empty($params['create_time_begin'])){
                rsp_die_json(10002,"创建开始时间不能为空");
            }
            if(empty($params['create_time_end'])){
                rsp_die_json(10002,"创建结束时间不能为空");
            }
            $post['create_time_begin'] = $params['create_time_begin'];
            $post['create_time_end'] = $params['create_time_end'];
            $post['postfix'] = date('Ym', strtotime($params['create_time_begin']));
        }
        if(empty($post['postfix'])){
            $date = date("Ym",time());
            $post['time_begin'] = date('Y-m-d 00:00:00',strtotime("first day of ".$date));
            $post['time_end'] = date('Y-m-d 23:59:59',strtotime("last day of ".$date));
            unset($post['tabletime']);
            $post['postfix'] = $date;
        }
        $this->changeInputParams($params['user_type_tag_id'] ?? 0,$params);
        log_message('==========lrs_test=========1'.json_encode($params));
        //下单人姓名、手机号搜索
        $create_client_ids = $this->getCreateClientIdsViaMobile($params,'create');
        if( $create_client_ids ){
            $post['create_client_ids']  = $create_client_ids;
        }
        log_message('==========lrs_test=========2'.json_encode($create_client_ids));
        $create_client_ids = $this->getCreateClientIdsViaFullName($params,'create');
        if( $create_client_ids ){
            $post['create_client_ids']  = isset($post['create_client_ids'])
                ? array_intersect($post['create_client_ids'],$create_client_ids)
                : $create_client_ids;
        }
        log_message('==========lrs_test=========3'.json_encode($create_client_ids));
        //业主姓名、手机号搜索
        $house_ids = $this->getHouseIds($params);
        if( $house_ids ){
            $post['house_ids']  = $house_ids;
        }
        log_message('==========lrs_test=========4'.json_encode($house_ids));
        //缴费人姓名、手机号搜索
        $paid_client_ids = $this->getCreateClientIdsViaMobile($params,'paid');
        if( $paid_client_ids ){
            $post['paid_client_ids']  = $paid_client_ids;
        }
        log_message('==========lrs_test=========5'.json_encode($paid_client_ids));
        $paid_client_ids = $this->getCreateClientIdsViaFullName($params,'paid');
        if( $paid_client_ids ){
            $post['paid_client_ids']  = isset($post['paid_client_ids'])
                ? array_intersect($post['paid_client_ids'],$paid_client_ids)
                : $paid_client_ids;
        }
        log_message('==========lrs_test=========6'.json_encode($paid_client_ids));
        //姓名和手机号的【不限】查询
        $this->changeQueryParams($params['user_type_tag_id'] ?? 0,$post);
        log_message('==========lrs_test=========7'.json_encode($post));
        $page = $post['page']??1;
        $pagesize = $post['pagesize']??20;
        if(isset($params['third_tnum'])){
            $order_res = Comm_Pay::gateway('admin.order.lists', [
                'third_tnum'=>$params['third_tnum'],'tabletime'=>$post['postfix'],'page'=>$page,'pagesize'=>$pagesize]);
            if(0 != $order_res['code'] || ( 0==$order_res['code']  && empty($order_res['content']['lists'])) ){
                log_message('订单查询失败 :' . json_encode($order_res));
                rsp_success_json(["lists"=>[],'count'=>0,'total_amount'=>0]);
            }
            unset($post['third_tnum']);
            $post['business_tnum']  = $order_res['content']['lists'][0]['tnum'];
        }
        if(!empty($params['client_id'])){
            $client_show = $this->user->post('/client/show',['client_id'=>$params['client_id']]);
            if(empty($client_show) || $client_show['code']!=0){
                rsp_die_json(10002,"客户端信息查询失败");
            }
            if(empty($client_show['content']['user_id'])){
                rsp_die_json(10002,"未绑定手机号");
            }
            $client_lists = $this->user->post('/client/lists',['user_id'=>$client_show['content']['user_id']]);
            if($client_lists['code']!=0 || ($client_lists['code']==0 && empty($client_lists['content']))){
                rsp_die_json(10002,"客户端查询失败");
            }
            $post['all_client_ids'] = array_column($client_lists['content'],'client_id');
        }
        if(!empty($params['create_client_id'])){
            $create_client_show = $this->user->post('/client/show',['client_id'=>$params['create_client_id']]);
            if(empty($create_client_show) || $create_client_show['code']!=0){
                rsp_die_json(10002,"创建人客户端信息查询失败");
            }
            if(empty($create_client_show['content']['user_id'])){
                rsp_die_json(10002,"创建人未绑定手机号");
            }
            $create_client_lists = $this->user->post('/client/lists',['user_id'=>$create_client_show['content']['user_id']]);
            if($create_client_lists['code']!=0 || ($create_client_lists['code']==0 && empty($create_client_lists['content']))){
                rsp_die_json(10002,"创建人客户端查询失败");
            }
            $post['create_client_ids'] = array_column($create_client_lists['content'],'client_id');
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
            rsp_success_json($count['message']);
        }
        $total_amount_sum = $this->order->post('/order/sum',$post);
        if ($total_amount_sum['code']!=0){
            rsp_die_json(10002,$total_amount_sum['message']);
        }
        $amount_sum_params = $post;
        $amount_sum_params['filed'] = 'amount';
        $amount_sum = $this->order->post('/order/sum',$amount_sum_params);
        if ($amount_sum['code']!=0){
            rsp_die_json(10002,$amount_sum['message']);
        }
        $project_arr = array_unique(array_filter(array_column($result,'project_id')));
        $project_res = $this->pm->post('/project/lists',['project_ids'=>$project_arr]);
        if($project_res['code']!=0){
            rsp_die_json(10002,$project_res['message']);
        }
        $project_content = array_column($project_res['content'],null,'project_id');

        $business_tnum_arr = array_unique(array_filter(array_column($result,'business_tnum')));
        $business_tnum_res = Comm_Pay::gateway('admin.order.lists', ['tnums'=>$business_tnum_arr,'tabletime'=>$post['postfix'],
            'page'=>$page,'pagesize'=>$pagesize]);
        if(0 != $business_tnum_res['code'] ){
            rsp_die_json(10002,$business_tnum_res ['message']);
        }
        log_message('----cadmin.order.lists---'.json_encode($business_tnum_res));
        $business_tnum_content = array_column($business_tnum_res['content']['lists'],null,'tnum');

        $charge_source_content = array_column($result,'charge_source_tag_id');
        $channel_content = array_column($result,'channel_tag_id');
        $order_status_content = array_column($result,'order_status_tag_id');
        $trade_type_content = array_column($result,'trade_type_tag_id');
        $trade_source_content = array_column($result,'trade_source_tag_id');
        $tags = array_filter(array_merge($charge_source_content,$channel_content,$order_status_content,
            $trade_type_content,$trade_source_content));
        $tag_res = $this->tag->post('/tag/lists',['tag_ids'=>implode(',',$tags),'nolevel'=>'Y']);
        if($tag_res['code']!=0){
            rsp_die_json(10002,$tag_res['message']);
        }
        $tag_content = array_column($tag_res['content'],null,'tag_id');

        $client_arr = array_unique(array_filter(array_column($result,'client_id')));
        $create_client_arr = array_unique(array_filter(array_column($result,'create_client_id')));
        $clientRes = $this->user->post('/client/lists',['client_ids'=>array_merge($client_arr,$create_client_arr)]);
        if($clientRes['code']!=0){
            rsp_die_json(10002,$clientRes['message']);
        }
        $client_content = array_column($clientRes['content'],null,'client_id');
        log_message('----chargeOrder-1---'.json_encode($client_content));
        $employee_arr = array_column($clientRes['content'],'employee_id');
        $employee_res = $this->user->post('/employee/lists',['employee_ids'=>$employee_arr]);
        if($employee_res['code']!=0){
            rsp_die_json(10002,$employee_res['message']);
        }
        $employee_content =  array_column($employee_res['content'],null,'employee_id');
        log_message('----chargeOrder-2---'.json_encode($employee_content));
        $user_arr = array_column($clientRes['content'],'user_id');
        $user_res = $this->user->post('/tenement/userlist',['user_ids'=>$user_arr]);
        if($user_res['code']!=0){
            rsp_die_json(10002,$user_res['message']);
        }
        log_message('---posLists----'.json_encode($user_res));
        $tenement_content = [];
        if(!empty($user_res['content']['lists'])){
            $tenement_content =  array_column($user_res['content']['lists'],null,'user_id');
        }
        $house_arr = array_unique(array_filter(array_column($result,'house_id')));
        $houseRes = $this->pm->post('/house/lists',['house_ids'=>$house_arr]);
        if($houseRes['code']!=0){
            rsp_die_json(10002,$houseRes['message']);
        }
        $house_content = array_column($houseRes['content'],null,'house_id');
        foreach ($house_content as &$item){
            $house_property = ['owner'=>[],'co'=>[]];
            foreach ($item['house_property'] as $key=>$value){
                if($value['proprietor_type'] == 'owner'){
                    $house_property['owner'][] = $value;
                }else{
                    $house_property['co'][] = $value;
                }
            }
            $item['house_property'] = $house_property;
        }

        foreach($result as $k=>$v){
            $result[$k]['amount'] =  $result[$k]['amount']/100;
            $result[$k]['total_amount'] =  $result[$k]['total_amount']/100;
            $result[$k]['paid_time'] = !empty($v['paid_time']) ? date('Y-m-d H:i:s',$v['paid_time']):"";
            $result[$k]['project_name'] =isset($project_content[$v['project_id']])?$project_content[$v['project_id']]['project_name']:'';
            $result[$k]['charge_source_tag_name'] = isset($tag_content[$v['charge_source_tag_id']])?$tag_content[$v['charge_source_tag_id']]['tag_name']:'';
            $result[$k]['channel_tag_name'] =isset($tag_content[$v['channel_tag_id']])?$tag_content[$v['channel_tag_id']]['tag_name']:'';
            $result[$k]['order_status_tag_name'] =isset($tag_content[$v['order_status_tag_id']])?$tag_content[$v['order_status_tag_id']]['tag_name']:'';
            $result[$k]['trade_type_tag_name'] =isset($tag_content[$v['trade_type_tag_id']])?$tag_content[$v['trade_type_tag_id']]['tag_name']:'';
            $result[$k]['trade_source_tag_name'] =isset($tag_content[$v['trade_source_tag_id']])?$tag_content[$v['trade_source_tag_id']]['tag_name']:'';
            $result[$k]['owner_name'] =  !empty($house_content[$v['house_id']]['house_property']['owner'])?$house_content[$v['house_id']]['house_property']['owner'][0]['proprietor_name']:'';
            $result[$k]['owner_mobile'] = !empty($house_content[$v['house_id']]['house_property']['owner'])?$house_content[$v['house_id']]['house_property']['owner'][0]['proprietor_mobile']:'';
            $result[$k]['third_tnum'] =isset($business_tnum_content[$v['business_tnum']])?$business_tnum_content[$v['business_tnum']]['third_tnum']:'';
            if(!empty($client_content[$v['client_id']])){
                if(!empty($client_content[$v['client_id']]['employee_id'])){
                    log_message('----chargeOrder-3---'.json_encode($v));
                    $result[$k]['paid_user_name'] = !empty($employee_content[$client_content[$v['client_id']]['employee_id']])?$employee_content[$client_content[$v['client_id']]['employee_id']]['full_name']:'';
                    $result[$k]['paid_user_mobile'] = !empty($employee_content[$client_content[$v['client_id']]['employee_id']])?$employee_content[$client_content[$v['client_id']]['employee_id']]['mobile']:'';
                }else if(!empty($client_content[$v['client_id']]['user_id']) && empty($client_content[$v['client_id']]['employee_id']) ){
                    $result[$k]['paid_user_name'] = !empty($tenement_content[$client_content[$v['client_id']]['user_id']])?$tenement_content[$client_content[$v['client_id']]['user_id']]['real_name']:"";
                    $result[$k]['paid_user_mobile'] = !empty($tenement_content[$client_content[$v['client_id']]['user_id']])?$tenement_content[$client_content[$v['client_id']]['user_id']]['mobile']:"";
                }else{
                    $result[$k]['paid_user_mobile'] = '';
                    $result[$k]['paid_user_name'] = '';
                }
            }else{
                $result[$k]['paid_user_mobile'] = '';
                $result[$k]['paid_user_name'] = '';
            }
            if(!empty($client_content[$v['create_client_id']])){
                if(!empty($client_content[$v['create_client_id']]['employee_id'])){
                    $result[$k]['create_user_name'] = !empty($employee_content[$client_content[$v['create_client_id']]['employee_id']])
                        ?$employee_content[$client_content[$v['create_client_id']]['employee_id']]['full_name']:'';
                    $result[$k]['create_user_mobile'] = !empty($employee_content[$client_content[$v['create_client_id']]['employee_id']])
                        ?$employee_content[$client_content[$v['create_client_id']]['employee_id']]['mobile']:'';
                }else if(!empty($client_content[$v['client_id']]['user_id']) && empty($client_content[$v['client_id']]['employee_id']) ){
                    $result[$k]['create_user_name']  = !empty($tenement_content[$client_content[$v['client_id']]['user_id']]['real_name'])
                        ?$tenement_content[$client_content[$v['client_id']]['user_id']]['real_name']:'';
                    $result[$k]['create_user_mobile']= !empty($tenement_content[$client_content[$v['client_id']]['user_id']]['mobile'])?
                        $tenement_content[$client_content[$v['client_id']]['user_id']]['mobile']:'';
                }else{
                    $result[$k]['create_user_mobile'] = '';
                    $result[$k]['create_user_name'] = '';
                }
            }else{
                $result[$k]['create_user_mobile'] = '';
                $result[$k]['create_user_name'] = '';
            }
        }
        rsp_success_json(["lists"=>$result,'count'=>$count['content'],'total_amount'=>$total_amount_sum['content']/100,
            'amount'=>((float)$amount_sum['content'])/100]);
    }
    
    private function changeInputParams($user_type_tag_id, &$params)
    {
        switch ($user_type_tag_id) {
            case 1366: //下单人
            {
                $params['create_user_name'] = $params['full_name'] ?? null;
                $params['create_user_mobile'] = $params['mobile'] ?? null;
                break;
            }
            case 1367: //业主
            {
                $params['owner_name'] = $params['full_name'] ?? null;
                $params['owner_mobile'] = $params['mobile'] ?? null;
                break;
            }
            case 1368://缴费人
            {
                $params['paid_user_name'] = $params['full_name'] ?? null;
                $params['paid_user_mobile'] = $params['mobile'] ?? null;
                break;
            }
            default:{//不限
                $params['owner_name'] = $params['full_name'] ?? null;
                $params['create_user_name'] = $params['full_name'] ?? null;
                $params['paid_user_name'] = $params['full_name'] ?? null;
                $params['owner_mobile'] = $params['mobile'] ?? null;
                $params['create_user_mobile'] = $params['mobile'] ?? null;
                $params['paid_user_mobile'] = $params['mobile'] ?? null;
            }
        }
        unset($params['full_name']);
        unset($params['mobile']);
        $params = array_filter($params,function ($m){
            return !is_null($m);
        });
    }
    
    /**
     * 通过手机号码查询下单人信息
     * @param $params array
     * @param $type string
     * @return array
     */
    private function getCreateClientIdsViaMobile(&$params, $type = '')
    {
        $query = [];
        if (
            $type === 'create'
            && isset($params['create_user_mobile'])
            && mb_strlen($params['create_user_mobile']) > 1
        ) {
            if (!check_mobile($params['create_user_mobile'])) {
                rsp_die_json(10001, '手机号格式错误,必须为纯数字,且不超过11位');
            }
            $query['mobile_f'] = $params['create_user_mobile'];
            unset($params['create_user_mobile']);
        } elseif (
            $type === 'paid'
            && isset($params['paid_user_mobile'])
            && mb_strlen($params['paid_user_mobile']) > 1
        ) {
            if (!check_mobile($params['paid_user_mobile'])) {
                rsp_die_json(10001, '手机号格式错误,必须为纯数字,且不超过11位');
            }
            $query['mobile_f'] = $params['paid_user_mobile'];
            unset($params['paid_user_mobile']);
        }
        $query = array_filter($query,function ($m){
            return !is_null($m);
        });
        if (empty($query)) {
            return [];
        }
        $query['page'] = 1;
        $query['pagesize'] = 100;
        $query['app_id'] = $_SESSION['oauth_app_id'] ?? '';
        $user_res = $this->user->post('/user/lists', $query);
        if (!isset($user_res['code']) || 0 != $user_res['code']) {
            log_message('下单人信息查询失败¹ :'.json_encode($user_res));
            rsp_die_json(10002, '下单人信息查询失败¹ '.($user_res['message'] ?: ''));
        }
        $user_ids = array_unique(array_filter(array_column($user_res['content'], 'user_id')));
        if (empty($user_ids)) {
            return ['888888888888888888888888'];
        }
        $client_res = $this->user->post('/client/lists', [
            'user_ids' => $user_ids,
            'page' => 1,
            'pagesize' => count($user_ids)
        ]);
        if (!isset($client_res['code']) || 0 != $client_res['code']) {
            log_message('下单人信息询失败² :'.json_encode($client_res));
            rsp_die_json(10002, '下单人信息查询失败² '.($client_res['message'] ?: ''));
        }
        $client_ids = array_unique(array_filter(array_column($client_res['content'], 'client_id')));
        return $client_ids ?: ['888888888888888888888888'];
    }
    
    /**
     * 通过姓名查询下单人信息
     * @param $params array
     * @param $type string
     * @return array
     */
    private function getCreateClientIdsViaFullName(&$params, $type = '')
    {
        $query = [];
        if ($type === 'create' && isset($params['create_user_name'])) {
            $query['full_name_f'] = $params['create_user_name'];
            unset($params['create_user_name']);
        } elseif ($type === 'paid' && isset($params['paid_user_name'])) {
            $query['full_name_f'] = $params['paid_user_name'];
            unset($params['paid_user_name']);
        }
        $query = array_filter($query,function ($m){
            return !is_null($m);
        });
        if (empty($query)) {
            return [];
        }
        $query['page'] = 1;
        $query['pagesize'] = 100;
        $query['app_id'] = $_SESSION['oauth_app_id'] ?? '';
        $employee_res = $this->user->post('/employee/lists', $query);
        if (!isset($employee_res['code']) || 0 != $employee_res['code']) {
            log_message('下单人信息查询失败³ :'.json_encode($employee_res));
            rsp_die_json(10002, '下单人信息查询失败¹ '.($employee_res['message'] ?: ''));
        }
        $employee_ids = array_unique(array_filter(array_column($employee_res['content'], 'employee_id')));
        if (empty($employee_ids)) {
            return ['888888888888888888888888'];
        }
        $client_res = $this->user->post('/client/lists', [
            'employee_ids' => $employee_ids,
            'page' => 1,
            'pagesize' => count($employee_ids)
        ]);
        if (!isset($client_res['code']) || 0 != $client_res['code']) {
            log_message('下单人信息查询失败 :'.json_encode($client_res));
            rsp_die_json(10002, '下单人信息查询失败 '.($client_res['message'] ?: ''));
        }
        $client_ids = array_unique(array_filter(array_column($client_res['content'], 'client_id')));
        return $client_ids ?: ['888888888888888888888888'];
    }
    
    /**
     * 获取 house_id 集合
     * @param $params
     * @return array
     */
    private function getHouseIds($params)
    {
        $query = [];
        $query['proprietor_name_f'] = $params['owner_name'] ?? null;
        if (isset($params['owner_mobile']) && mb_strlen($params['owner_mobile']) > 0) {
            if (!check_mobile($params['owner_mobile'])) {
                rsp_die_json(10001, '手机号格式错误,必须为纯数字,且不超过11位');
            }
            $query['proprietor_mobile_f'] = $params['owner_mobile'];
        }
        $query = array_filter($query,function ($m){
            return !is_null($m) && $m !== '';
        });
        if (empty($query)) {
            return [];
        }
        $query['proprietor_type'] = 'owner';
        $query['page'] = 1;
        $query['pagesize'] = 100;

        if(isset($query['project_ids']) && $query['project_ids'] != 'all'){
            $query['project_id'] = $this->project_id;
        }

        $result = $this->pm->post('/house/property/lists', $query);
        if (!isset($result['code']) || 0 != $result['code']) {
            rsp_die_json(10002, '业主信息查询失败 '.($result['message'] ?: ''));
        }
        $house_ids = array_unique(array_filter(array_column($result['content'], 'house_id')));
        return $house_ids ?: ['888888888888888888888888'];
    }
    
    /**
     * 用户和手机号的【不限】搜索支持
     * @param $user_type_tag_id
     * @param $params
     * @return null
     */
    private function changeQueryParams($user_type_tag_id, &$params)
    {
        unset($params['mobile']);
        unset($params['full_name']);
        unset($params['user_type_tag_id']);
        unset($params['owner_name']);
        unset($params['create_user_name']);
        unset($params['paid_user_name']);
        unset($params['owner_mobile']);
        unset($params['create_user_mobile']);
        unset($params['paid_user_mobile']);
        if (in_array((int)$user_type_tag_id, [1366, 1367, 1368])) {
            return null;
        }
        if (isTrueKey($params, 'create_client_ids')) {
            $params['create_client_ids_or'] = $params['create_client_ids'];
        }
        if (isTrueKey($params, 'house_ids')) {
            $params['house_ids_or'] = $params['house_ids'];
        }
        if (isTrueKey($params, 'paid_client_ids')) {
            $params['paid_client_ids_or'] = $params['paid_client_ids'];
        }
        unset($params['create_client_ids']);
        unset($params['paid_client_ids']);
        unset($params['house_ids']);
    }
    
    
    public function posOrderSublists($params = [])
    {
        unsetEmptyParams($params);
        $post = $params;
        $result = $this->order->post('/order/lists',$post);
        $result = ($result['code'] === 0 && $result['content']) ? $result['content'] : [];
        if (!$result) rsp_success_json([]);
        $tnum_arrs = array_column($result,'tnum');
        $subOrder = $this->order->post('/suborder/lists',['tnums' => $tnum_arrs]);
        $subOrder = ($subOrder['code'] === 0 && $subOrder['content']) ? $subOrder['content'] : [];
        $result = array_column($result,null,'tnum');
        foreach ($result as $kl=>$vl){
            $result[$kl]['paid_time'] =  !empty($result[$kl]['paid_time']) ? date('Y-m-d H:i:s',$result[$kl]['paid_time']):"";
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
        if (!isTrueKey($params,...['tnum'])) rsp_error_tips(10001, 'tnum');
        $post = [
            'tnum' => $params['tnum'],
        ];
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
        if (!isTrueKey($params,...['tnum'])) rsp_error_tips(10001, 'tnum');
        $post = [
            'tnum' => $params['tnum'],
        ];
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
            $subOrder[$k]['amount'] =  $v['amount']/100;
            $subOrder[$k]['total_amount'] = $v['total_amount']/100;
            $subOrder[$k]['penal_amout'] = $v['penal_amout']/100;
            $subOrder[$k]['penal_total_amout'] =  $v['penal_total_amout']/100;
        }
        rsp_success_json($subOrder);
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

}