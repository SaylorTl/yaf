<?php

include __DIR__ . '/Basecharging.php';

class Charge extends Basecharging
{

    /**
     * @param array $params
     * @throws ReflectionException
     * 新版计费
     */
    public function new_cost($params = [])
    {
        log_message(__METHOD__ . '------' . json_encode($params));
        $obj = new \Charging\App\Zhsq\BasicModel($params);
        $obj->getChargeLists();
    }

    public function detail($params = [])
    {
        log_message(__METHOD__ . '------' . json_encode($params));
        $result = $this->getChargeLists($params);
        rsp_success_json($result, '请求成功');
    }

    public function detail_limit($params = [])
    {
        log_message(__METHOD__ . '------' . json_encode($params));
        if (isTrueKey($params, 'client_id') == false) rsp_die_json(10001, '参数缺失');
        $config = getConfig('other.ini');
        $time = $config->get('charge.detail_see_time_limit') ?: 600;
        $times_limit = $config->get('charge.detail_times_limit') ?: 5;
        $redis = Comm_Redis::getInstance();
        $redis->select(8);
        //由于多个用户端都只能查看5次，故key不拼上openid
        $key = isset($_SESSION['user_id']) && $_SESSION['user_id'] ? 'ADAPTER_' . $_SESSION['user_id'] : 'ADAPTER_' . $params['client_id'];
        $redis->INCRBY($key, 1);
        $redis->expire($key, $time);

        $count = $redis->get($key);
        if ($count > $times_limit) rsp_die_json(10002, '您无权限查看费用详情，请进行业主认证');
        rsp_success_json('', 'success');
    }

    public function print_receipt($params = [])
    {
        try {
            log_message(__METHOD__ . '----' . json_encode($params));
            if (isTrueKey($params, 'tnum') == false) rsp_die_json(10001, '参数缺失');
            $receipt_show = $this->adapter->post('/bill/receipt/show', ['tnum' => $params['tnum']]);

            $result = $this->_getAttach($params['tnum']);

            $attach = $result['attach'];
            $order_show = $result['order_show'];
            if (empty($attach)) rsp_die_json(10002, '订单附加数据有误');
            if (!isset($attach['collect_penalty'])) rsp_die_json(10002, '订单附加数据有误2');

            $project_show = $this->pm->post('/project/show', ['project_id' => $attach['project_id']]);
            if (0 !== (int)$project_show['code'] || !$project_show['content']) {
                rsp_die_json(10002, '项目信息查询失败');
            }
            if ($project_show['content']['support_receipt'] != 'Y') {
                rsp_die_json(10002, '该项目暂不支持开具电子收据');
            }

            //文件服务对象
            $file_object = new \Receipt\FileModel();
            if ($receipt_show['code'] == 0 && !empty($receipt_show['content'])) {
                //文件读取
                $info = $file_object->read($receipt_show['content']['file_id']);
                rsp_success_json(['file_addr' => $info['url']], 'success');
            }

            //查询签章信息
            $signature_show = $this->pm->post('/signature/show', [
                'project_id' => $attach['project_id'],
                'signature_type' => 'R',
                'status' => 'Y',
            ]);
            if ($signature_show['code'] != 0 || empty($signature_show['content'])) rsp_die_json(10003, '项目签章信息不存在');
            //查询客户管理信息
            $company_show = $this->company->post('/corporate/lists', [
                'company_ids' => [$signature_show['content']['property_company_id']]
            ]);
            if ($company_show['code'] != 0 || empty($company_show['content'])) rsp_die_json(10003, '物业公司信息不存在');
            $signature_show['content']['company_name'] = $company_show['content'][0]['company_name'];
            $signature_show['content']['collect_penalty'] = $attach['collect_penalty'];

            //查询产权人信息
            $house_show = $this->pm->post('/house/property/detail', [
                'project_id' => $attach['project_id'],
                'house_id' => $attach['house_id'],
            ]);
            if ($house_show['code'] != 0 || empty($house_show['content'])) rsp_die_json(10003, '房产信息不存在');
            $owner = [];
            foreach ($house_show['content'] as $item) {
                foreach ($item['house_property'] as $key => $value) {
                    if ($value['proprietor_type'] == 'owner') {
                        $owner = $value;
                    }
                }
            }
            //查询（旧平台）订单信息
            $bill_show = $this->adapter->post('/bill/one', [
                'project_id' => $attach['project_id'],
                'house_room' => trim($house_show['content'][0]['house_room']),
                'paidtime' => $order_show['paid_time'],
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
            $receipt_obj = new \Receipt\ReceiptModel($params['tnum'], $data, $order_show['paid_time'], $signature_show['content'], $owner);
            $receipt_md5 = $receipt_obj->get_params();
            //文件上传
            $file_id = $file_object->upload('receipt', $file_path);
            //文件读取
            $info = $file_object->read($file_id);
            unlink($file_path);

            //订单号与文件id映射关系添加
            $result = $this->adapter->post('/bill/receipt/add', [
                'tnum' => $params['tnum'],
                'file_id' => $file_id,
                'receipt_md5' => $receipt_md5,
            ]);
            log_message(__METHOD__.'---receipt:'.json_encode([$result]));
            if ($result['code'] != 0) {
                rsp_die_json(10004, '电子收据保存失败');
            }
            rsp_success_json(['file_addr' => $info['url']], 'success');
        } catch (\Exception $e) {
            log_message('打印收据异常----MSG=' . $e->getMessage() . '---订单号:' . $params['tnum']);
            rsp_die_json('10004', '电子收据查看失败，请稍后重试');
        }
    }

    /**
     * 是否支持收据打印
     * @param array $params
     */
    public function support_receipt($params = [])
    {
        log_message(__METHOD__ . '----' . json_encode($params));
        if (isTrueKey($params, 'tnum') == false) rsp_die_json(10001, '参数缺失');

        $result = $this->_getAttach($params['tnum']);
        $attach = $result['attach'];
        log_message('-xxxx---' . json_encode([$attach]));

        if (empty($attach)) rsp_die_json(10002, '物业费附加信息不存在');

        //查询项目信息
        $project_show = $this->pm->post('/project/show', ['project_id' => $attach['project_id']]);
        if ($project_show['code'] != 0 || !$project_show['content']) rsp_die_json(10002, '项目信息不存在');
        rsp_success_json($project_show['content'], 'success');
    }

    /**
     * 提供给测试，查询欠费的房子
     * @param array $params
     */
    public function arrears_test($params = [])
    {
        log_message(__METHOD__ . '----' . json_encode($params));
        if (isTrueKey($params, 'project_id', 'space_name') == false) rsp_die_json(10001, '参数缺失');
        $data = [
            'project_id' => $params['project_id'],
            'space_name' => $params['space_name'],
        ];
        if (isTrueKey($params, 'house_room')) $data['house_room'] = $params['house_room'];
        $result = $this->adapter->post('/bill/arrears/customers', $data);
        if (!$result || (int)$result['code'] != 0) rsp_die_json(10002, '查询失败');
        rsp_success_json($result['content'], '查询成功');
    }


    public function convert_img($params = [])
    {
        log_message(__METHOD__ . '----' . json_encode($params));
        if (isTrueKey($params, 'tnum') == false) rsp_die_json(10001, '参数缺失');

        $receipt_show = $this->adapter->post('/bill/receipt/show', ['tnum' => $params['tnum']]);
        if (0 !== (int)$receipt_show['code'] || !$receipt_show['content']) {
            rsp_die_json(10001, '收据信息不存在');
        }

        $file_path = $this->_download_file($receipt_show['content']['file_id']);
        $tmp = "which convert";
        exec('which convert', $tmp, $code);

        if (0 !== (int)$code) {
            rsp_die_json(10001, '内部错误1001');
        }
        $bin_path = $tmp[0];
        $file_name = md5(time() . rand(10000, 99999)) . '.jpg';
        $image_path = DATA_PATH . '/temp/' . $file_name;
        $shell_cmd = $bin_path . " -verbose -density 150 -trim " . $file_path . " -quality 100 -flatten -sharpen 0x1.0 " . $image_path;
        exec($shell_cmd, $tmp, $code);

        unlink($file_path);
        if (0 !== (int)$code) {
            rsp_die_json(10001, '内部错误1002');
        }

        $info = file_get_contents($image_path);
        unlink($image_path);

        rsp_success_json(['base64_image' => chunk_split(base64_encode($info))]);
    }

    public function send_mail($params = [])
    {
        try {
            log_message(__METHOD__ . '----' . json_encode($params));
            if (isTrueKey($params, 'tnum', 'user_email') == false) rsp_die_json(10001, '参数缺失');

            $user_id = $_SESSION['user_id'] ?? '';
            if (!$user_id) {
                rsp_die_json(10001, '用户信息缺失');
            }

            $receipt_show = $this->adapter->post('/bill/receipt/show', ['tnum' => $params['tnum']]);
            if (0 !== (int)$receipt_show['code'] || !$receipt_show['content']) {
                rsp_die_json(10001, '收据信息不存在');
            }

            $file_path = $this->_download_file($receipt_show['content']['file_id']);

            $body = '您好！您的电子收据已送达，麻烦您查收下附件，谢谢！祝您生活愉快！';
            $result = send_email($params['user_email'], '电子收据', $body, [$file_path]);
            log_message('---send_mail_result------' . json_encode([$result]));
            unlink($file_path);

            if (!$result) {
                rsp_die_json(10002, '邮件发送失败，稍后再试');
            }

            //todo 用户邮箱存储
            $result = $this->user->post('/userext/add', [
                'user_id' => $user_id,
                'detail' => $params['user_email'],
                'user_ext_tag_id' => 1178
            ]);

            rsp_success_json(1);

        } catch (\Exception $e) {
            log_message('-----异常信息------' . $e->getMessage());
            rsp_die_json(10002, '邮件发送失败，稍后再试');
        }
    }
    
    public function history_email($params = [])
    {
        $user_id = $_SESSION['user_id'] ?? '';
        if (!$user_id) {
            rsp_die_json(10001, '用户信息缺失');
        }
        $tmp = $this->user->post('/userext/lists', ['user_id' => $user_id, 'user_ext_tag_id' => 1178]);
        if (0 !== $tmp['code'] || !$tmp['content']) {
            rsp_success_json(['lists' => []], 'success');
        }

        $lists = array_map(function ($m) {
            $m['user_mail'] = $m['detail'];
            return $m;
        }, $tmp['content']);

        rsp_success_json(['lists' => $lists], 'success');
    }

    private function _download_file($file_id)
    {
        try {
            $file_object = new \Receipt\FileModel();
            $info = $file_object->read($file_id);
            $pdf = $file_object->download($info['url']);

            $file_name = md5(time() . rand(10000, 99999)) . '.pdf';
            $tmp_path = DATA_PATH . '/temp/' . $file_name;
            file_put_contents($tmp_path, $pdf);

            return $tmp_path;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}


