<?php

final class Invoice extends Base
{

    /**
     * @param array $params
     * 查询用户开票信息
     */
    public function show($params = [])
    {
        $params['page'] = 1;
        $params['pagesize'] = 1;

        if (isset($params['user_id']) && $params['user_id'] == 0) {
            unset($params['user_id']);
        }

        if (isset($params['employee_id']) && $params['employee_id'] == 0) {
            unset($params['employee_id']);
        }

        if (!isTrueKey($params, 'employee_id') && !isTrueKey($params, 'user_id')) {
            rsp_die_json(10003, 'employee_id或user_id必须有一个');
        }

        $data = $this->user->post('/userinvoce/lists', $params);
        if ($data['code'] !== 0) rsp_error_tips(10002);
        if (empty($data['content'])) rsp_success_json([], $data['message']);

        rsp_success_json($data['content'][0], '查询成功');
    }

    /**
     * @param array $params
     * 检查订单是否开过发票
     */
    public function check($params = [])
    {
        if (isTrueKey($params, 'tnum') == false) {
            rsp_die_json(10001, '缺少订单号');
        }

        $show = $this->woDetail($params['tnum']);
        if ($show['code'] == 0 && !empty($show['content'])) {
            rsp_die_json(10002, '您已提交申请，请耐心等待管理员送票上门。');
        }

        rsp_success_json(1);
    }

    /**
     * @param array $params
     * 发票申请
     */
    public function apply($params = [])
    {
        if (isTrueKey($params, 'tnum') == false) {
            rsp_die_json(10001, '缺少订单号');
        }

        if (!isTrueKey($params, 'from_id')) {
            rsp_die_json(10001, '缺少from_id');
        }

        if (!isTrueKey($params, 'invoice_data')) {
            rsp_die_json(10001, '缺少开票信息');
        }

        if (!isTrueKey($params, 'apply_mobile')) {
            rsp_die_json(10001, '缺少申请人手机号');
        }

        if (!is_array($params['invoice_data'])) {
            rsp_die_json(10001, '开票信息数据格式错误');
        }

        $invoice_data = $params['invoice_data'];

        if (!is_not_empty($invoice_data, 'invoce_title')) {
            rsp_die_json(10001, '缺少发票抬头');
        }

        if (!isTrueKey($invoice_data, 'invoce_type')) {
            rsp_die_json(10001, '缺少发票类型');
        }

        if ($invoice_data['invoce_type'] == 'company') {
            if (!is_not_empty($invoice_data, 'tax_num')) {
                rsp_die_json(10001, '缺少税号');
            }
        }

        $order = $this->order->post('/order/show', ['business_tnum' => $params['tnum']]);
        $order = ($order['code'] === 0 && $order['content']) ? $order['content'] : [];
        if (!$order) {
            rsp_die_json(10002, '未查询到订单信息');
        }

        $show = $this->woDetail($params['tnum']);
        if ($show['code'] == 0 && !empty($show['content'])) {
            rsp_die_json(10002, '您已提交申请，请耐心等待管理员送票上门。');
        }

        $project = $this->pm->post('/project/projects', ['project_id' => $order['project_id']]);
        if ($project['code'] != 0 || empty($project['content'])) {
            rsp_die_json(10002, '查询项目信息失败-' . $project['message']);
        }

        //记录开票信息
        $invoice_params = [
            'invoce_type' => $invoice_data['invoce_type'], 'invoce_title' => $invoice_data['invoce_title'],
            'tax_num' => $invoice_data['tax_num'] ?? '',
            'mobile' => $invoice_data['mobile'] ?? '',
            'email' => $invoice_data['email'] ?? '',
            'employ_address' => $invoice_data['employ_address'] ?? '',
            'bank_name' => $invoice_data['bank_name'] ?? '',
            'bank_account' => $invoice_data['bank_account'] ?? '',
            'is_default' => 'Y',
        ];

        $invoice_show_params = ['page' => 1, 'pagesize' => 1];

        if (!isTrueKey($invoice_data, 'user_id') && !isTrueKey($invoice_data, 'employee_id')) {
            rsp_die_json(10001, '缺少user_id或employee_id');
        }

        $user_id = $employee_id = '';
        if (isTrueKey($invoice_data, 'user_id')) {
            $user_id = $invoice_data['user_id'];
            $invoice_params['user_id'] = $user_id;
            $invoice_show_params['user_id'] = $user_id;
        }

        if (isTrueKey($invoice_data, 'employee_id')) {
            $employee_id = $invoice_data['employee_id'];
            $invoice_params['employee_id'] = $employee_id;
            $invoice_show_params['employee_id'] = $employee_id;
        }

        //查询是否已有开票信息
        $invoice_show = $this->user->post('/userinvoce/lists', $invoice_show_params);
        if ($invoice_show['code'] == 0 && !empty($invoice_show['content'])) {
            //更新开票信息
            $invoice_params['user_invoce_id'] = $invoice_show['content'][0]['user_invoce_id'];
            $this->user->post('/userinvoce/update', $invoice_params);
        } else {
            //添加开票信息
            $invoice_add = $this->user->post('/userinvoce/add', $invoice_params);
            if ($invoice_add['code'] != 0) {
                rsp_die_json(10002, '添加开票信息失败--' . $invoice_add['message']);
            }
        }
        $from_id = $params['from_id'];
        //添加工单
        $wb_result = $this->workBookCreate($order, $invoice_params, $project['content'][0], $params['apply_mobile'], $user_id, $employee_id, $from_id);
        if ((int)$wb_result['code'] !== 0 || empty($wb_result['content'])) {
            rsp_die_json(10002, '开票工单创建失败-' . $wb_result['message']);
        }

        //发布工单
        $publish = $this->publish($wb_result['content']);
        if ((int)$publish['code'] !== 0 || empty($publish['content'])) {
            rsp_die_json(10002, '开票工单发布失败-' . $publish['message']);
        }

        //推送消息
        $this->pushTiding($wb_result['content']);

        rsp_success_json('发票申请成功');
    }

    /**
     * @param $order
     * @param $invoice
     * @param $project
     * @param $apply_mobile
     * @param $user_id
     * @param $employee_id
     * @return mixed
     * 创建工单
     */
    private function workBookCreate($order, $invoice, $project, $apply_mobile, $user_id, $employee_id, $from_id)
    {
        $subOrder_content = "";
        $subOrder = $this->order->post('/suborder/lists', ['tnum' => $order['tnum']]);
        if ($subOrder['code'] === 0 && !empty($subOrder['content'])) {
            $temp = [];
            foreach ($subOrder['content'] as $kv => $vv) {

                if (isset($temp[$vv['year'] . "年" . $vv['tnum_month'] . "月"])) {
                    $temp[$vv['year'] . "年" . $vv['tnum_month'] . "月"] += $vv['amount'];
                } else {
                    $temp[$vv['year'] . "年" . $vv['tnum_month'] . "月"] = $vv['amount'];
                }
            }
            foreach ($temp as $key => $item) {
                $subOrder_content .= $key . ' ' . ($item / 100) . '元' . PHP_EOL;
            }
            $subOrder_content = rtrim($subOrder_content, PHP_EOL);
        }
        $manage_from_id = $this->getManageFromId();
        $amont = $order['amount'] / 100;
        $attach = json_decode($order['attach'], true);
        $paid_time = date('Y-m-d H:i:s', $order['paid_time']);
        $invoice_type = $invoice['invoce_type'] == 'person' ? '个人' : '公司';
        $company_text = '';
        $employ_address = isset($invoice['employ_address']) ? $invoice['employ_address'] : '';
        $mobile = isset($invoice['mobile']) ? $invoice['mobile'] : '';
        $bank_name = isset($invoice['bank_name']) ? $invoice['bank_name'] : '';
        $bank_account = isset($invoice['bank_account']) ? $invoice['bank_account'] : '';
        if ($invoice['invoce_type'] == 'company') {
            $company_text = "税号：{$invoice['tax_num']}
                             公司地址：{$employ_address}
                             电话号码：{$mobile}
                             开户银行：{$bank_name}
                             银行账户：{$bank_account}
            ";
        }


        $content = "开票信息 
                    抬头类型 ：{$invoice_type}
                    发票抬头 ：{$invoice['invoce_title']}
                    {$company_text}
                    订单信息 
                    缴费地址 ：{$attach['address']}
                    费用类型 ：物业费
                    缴费金额 ：{$amont}元
                        {$subOrder_content}
                    支付时间 ：{$paid_time}
                    订单号 ：{$order['business_tnum']}
                    订单状态 ：已支付
        ";

        $title = '发票申请-' . date('Ymd') . '-' . $attach['address'];
        $operator = $employee_id ? $employee_id : $user_id;
        $performer = $this->getPerformer(['project_id' => $order['project_id'], 'space_id' => $order['space_id']]);

        $params = [
            'kind' => [
                1,
                70
            ],
            'multipart' => [
                'title' => $title,
                'priority' => 614,
                'expire_at' => date('Y-m-d H:i:s', strtotime('+7day')),
                'amount' => '',
                'content' => $content,
                'project' => [
                    'id' => $project['project_id'],
                    'name' => $project['project_name'],
                    'address' => ''
                ],
                'mobile' => $apply_mobile,
            ],
            'performer' => json_encode([
                's' => $performer//执行人  默认为项目负责人李星逸
            ]),
            'source' => 617,
            'subapp' => 22,
            'visibility' => 'private',
            'operator' => $operator, //发起人
            'sid' => $order['business_tnum'],
            'from_id' => $manage_from_id
        ];

        $config = getConfig('other.ini');
        $audience = $config->get('invoice.wos.audience.' . $order['project_id']);
        if($audience){
            $params['audience'] = json_encode([
                's' => $audience //永红源馨园小区关注人为钟静华
            ]);
        }

        $result = $this->wos->post('/create', json_encode($params));
        log_message('---invoice/apply-----创建发票工单------===' . json_encode([$result,$params]));
        return $result;
    }

    /**
     * @param $wb_result
     * 推送到消息中心
     */
    private function pushTiding($wb_result)
    {
        $push_data = [
            'sid' => $wb_result['_id'],
            'kind' => 'change_order',
            'title' => $wb_result['title'],
            'initiator' => $wb_result['initiator'],
            'audience' => json_encode($wb_result['audience']),
            'details' => $wb_result['details']['content']
        ];
        $this->tiding->post('/tiding/add', $push_data);
    }

    /**
     * @param $wb_result
     * @return mixed
     * 发布工单
     */
    private function publish($wb_result)
    {
        $publish_params = [
            '_id' => $wb_result['_id'],
            'operator' => $wb_result['initiator'],
            '__v' => $wb_result['__v']
        ];
        $publish = $this->wos->post('/publish', json_encode($publish_params));
        return $publish;
    }

    private function woDetail($tnum)
    {
        $config = getConfig('other.ini');
        $operator = $config->get('invoice.wos.performer.' . $this->oauth_app_id);
        if (!$operator) {
            rsp_die_json(10001, '未配置发票执行人');
        }
        $employee = $this->user->post('/employee/userlist', ['employee_id' => $operator]);
        log_message('---Invoice/woDetail-----查询发票负责人信息----employee_id===' . $operator . '--res===' . json_encode($employee));
        if ($employee['code'] != 0 || empty($employee['content']['lists'])) {
            rsp_die_json(10002, '获取执行人信息失败');
        }

        $frame_id = $employee['content']['lists'][0]['frame_id'];
        $show = $this->wos->get('/detail', ['sid' => $tnum, 'operator' => $operator, 'frame' => $frame_id]);
        log_message('---invoice/woDetail-----查询发票工单详情----$tnum===' . $tnum . '--res===' . json_encode($show));
        return $show;
    }

    private function getManageFromId()
    {
        $params = [
            'oauth_app_id' => $this->oauth_app_id,
            'app_type' => 'manage',
            'third_type' => 'wechat',
            'not_third_app_id' => 'Y'
        ];
        $result = (new Comm_Gateway())->gateway($params, 'admin.appbinding.show', ['service' => 'auth2']);

        log_message(__METHOD__ . '-----' . json_encode([$params, $result]));
        if (!$result || 0 != $result['code'] || !$result['content']) {
            rsp_die_json(10001, '查詢oauth_third_app_id信息失敗');
        }

        return $result['content']['oauth_third_app_id'];

    }

    private function getPerformer($data)
    {
        $config = getConfig('other.ini');
        $operator = $config->get('invoice.wos.performer.' . $this->oauth_app_id);
        $job_map = require_once(CONFIG_PATH . '/jobMap.php');
        if ($job_map) {
            $job_id = $job_map[$data['project_id']][$data['space_id']] ?? '';
            if ($job_id) {
                $employee = $this->user->post('/employee/userlist', [
                    'project_id' => $data['project_id'],
                    'job_tag_id' => $job_id
                ]);
                if ($employee['code'] == 0 && !empty($employee['content']['lists'])) {
                    $operator = $employee['content']['lists'][0]['employee_id'];
                }
            }
        }
        if (!$operator) {
            rsp_die_json(10001, '未配置发票执行人');
        }
        return $operator;
    }


}