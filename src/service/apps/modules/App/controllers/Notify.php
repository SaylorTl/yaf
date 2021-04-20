<?php
include_once APP_PATH."/BaseController.php";
use Project\ArrearsModel;
class NotifyController extends BaseController{
    const PAY_STATUS = [
        'CREATE' => 682,
        'FAIL' => 683,
        'SUCCESS' => 684,
        'CANCEL' => 685,
        'REFUND_ALL' => 687,
        'REFUND_PART' => 688,
    ];

    /**
     * 支付回调通知地址
     * 698 物业费
     * 697 停车费
     */
    protected $trade_notify_urls = [];

    protected $pm;

    protected  $order;
    public function init(){
        parent::init();
        $action = $this->getRequest()->getActionName();
        $content = file_get_contents('php://input');
        $post    = (array)json_decode($content, true);
        log_message('----sqNotify----'.json_encode($post,JSON_UNESCAPED_UNICODE));
        if (!method_exists(__CLASS__,$action)){
            log_message('----sqNotify error----'.'{回调method错误}');
            rsp_die_json(90002, 'Method does not exist');
        }

        $this->trade_notify_urls = getConfig('ms.ini')->get('nofityUrl')->toArray() ?: [];
        $this->$action($post);
    }


    public function payCallback($params)
    {
        log_message('----payCallback1----'.json_encode($params));
        $fields = [ 'tnum', 'status'];
        if (!isTrueKey($params, ...$fields)){
            rsp_setting(json_encode(['status'=>'FAIL']));
        }
        if(empty(self::PAY_STATUS[$params['status']])){
            rsp_setting(json_encode(['status'=>'FAIL']));
        }
        $status = self::PAY_STATUS[$params['status']];
        $order_url = getConfig('ms.ini')->get('order.url');
        $suborder_update_res = curl_json("post", $order_url.'/suborder/update', ['out_trade_tnum'=>$params['tnum'],
            'order_status_tag_id'=>$status,'paid_time'=>time()]);
        log_message('----payCallback2----'.json_encode($suborder_update_res));
        if($suborder_update_res['code']!=0){
            rsp_setting(json_encode(['status'=>'FAIL']));
        }
        $suborder_show_res = curl_json("post", $order_url.'/suborder/show', ['out_trade_tnum'=>$params['tnum']]);
        log_message('----payCallback3----'.json_encode($suborder_show_res));
        if($suborder_show_res['code']!=0){
            rsp_setting(json_encode(['status'=>'FAIL']));
        }

        $order_res = curl_json("post", $order_url.'/order/show', ['tnum'=>$suborder_show_res['content']['tnum']]);
        log_message('----payCallback4----'.json_encode($order_res));
        if($order_res['code']!=0){
            rsp_setting(json_encode(['status'=>'FAIL']));
        }

        $trade_notify_url = $this->trade_notify_urls[$order_res['content']['trade_source_tag_id']] ?? '';

        $pay_time = time();
        $order_update_res = curl_json("post", $order_url.'/order/update', ['tnum'=>$suborder_show_res['content']['tnum'],
            'order_status_tag_id'=>$status,'paid_time'=>$pay_time]);
        log_message('----payCallback5----'.json_encode($order_update_res));
        if ($order_update_res['code'] !== 0){
            rsp_setting(json_encode(['status'=>'FAIL']));
        }
        log_message('----payCallback6----'.json_encode($order_res));
        if('SUCCESS' === $params['status']){
            //推送订单数据
            Comm_EventTrigger::push('screen_push',[
                'method'=>'orderPaid',
                'project_id'=>$order_res['content']['project_id'],
                'data'=>json_encode($order_res['content'])
            ]);
            $pm = new Comm_Curl(['service' => 'pm', 'format' => 'json']);
            $project_res = $pm ->post('/project/show', ['project_id'=>$order_res['content']['project_id']]);
            if ($project_res['code'] !== 0){
                log_message('----payCallback7----'.json_encode($project_res));
                rsp_setting(json_encode(['status'=>'FAIL']));
            }
            log_message('----payCallback15----'.json_encode($project_res));
            $params['total_amount'] =   $order_res['content']['total_amount'];
            $params['amount'] =   $order_res['content']['amount'];
            $params['channel_tag_id'] =   $order_res['content']['channel_tag_id'];
            $params['attach'] =   $order_res['content']['attach'];
            $attach = json_decode($order_res['content']['attach'],true);
            if(698 == $order_res['content']['trade_source_tag_id'] ){
                log_message('----payCallback17----'.json_encode($project_res['content']['toll_system_tag_id']));
                switch($project_res['content']['toll_system_tag_id']){
                    case 1388:
                        $config = getConfig('ms.ini');
                        $jz_url = $config->get('jz.url');
                        log_message('----payCallback9----'.json_encode($params));
                        $pay_res = curl_json("post", $jz_url.'/pay/notice', json_encode($params,true),["Content-Type:application/json"]);
                        break;
                    case 1399:
                        log_message('----payCallback17----'.json_encode($params));
                        $cost =  new Comm_Curl([ 'service'=>'billing','format'=>'json']);
                        $billparams['billing_status_tag_id'] = '1505';
                        $billparams['receivable_bill_ids'] = $attach['receivable_bill_ids'];
                        $billparams['paid_time'] = $pay_time;
                        $billparams['tnum'] = $order_res['content']['business_tnum'];
                        log_message('----payCallback7----'.json_encode($billparams));
                        $pay_res =  $cost->post( '/pay/notice', $billparams);
                        //修改欠费记录
                        ArrearsModel::handle($attach);
                        break;
                    default:
                        log_message('----payCallback8----'.json_encode($params));
                        $pay_res =  curl_json("post", $trade_notify_url, json_encode($params,true),["Content-Type:application/json"]);
                        break;
                }
            }else if(697 == $order_res['content']['trade_source_tag_id']||1632 == $order_res['content']['trade_source_tag_id']){
                switch($order_res['content']['platform_type_tag_id'] ){
                    case 1525:
                        if(1509==$attach['rule_type_tag_id']){
                            $contract = new Comm_Curl([ 'service'=>'contract','format'=>'json']);
                            $contract_update_res = $contract->post('/contract/update',['contract_id'=>$attach['contract_id'],
                                'end_time'=>strtotime($attach['end_time'])]);
                            log_message('----payCallback10----'.json_encode($contract_update_res));
                        }else if(1510==$attach['rule_type_tag_id']){
                            $params['status'] = "SUCCESS";
                            log_message('----payCallback11----'.json_encode($params));
                            $es_result = Comm_EventTrigger::push('inout_temp_notify',$params);
                            log_message('----payCallback12----'.json_encode($es_result));
                        }
                        rsp_setting(json_encode(['status'=>'SUCCESS']));
                        break;
                    case 1526:
                        log_message('----payCallback13----'.json_encode($params));
                        $pay_res =  curl_json("post", $trade_notify_url, json_encode($params,true),["Content-Type:application/json"]);
                        rsp_setting(json_encode(['status'=>'SUCCESS']));
                        break;
                    default:
                        log_message('----payCallback14----'.json_encode($params));
                        $pay_res =  curl_json("post", $trade_notify_url, json_encode($params,true),["Content-Type:application/json"]);
                        break;
                }
            }
            if(empty($pay_res) || $pay_res['status'] == 'FAIL'){
                rsp_setting(json_encode(['status'=>'FAIL']));
            }
        }
        log_message('----payCallback16----'.json_encode($params));
        rsp_setting(json_encode(['status'=>'SUCCESS']));
    }
}
