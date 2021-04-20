<?php

class Transaction extends Base
{

    /**
     * 交易订单列表
     * @param array $params
     */
    public function lists($params = [])
    {
        try {
            log_message(__METHOD__ . '-----params=' . json_encode($params));
            if (isTrueKey($params, 'cano', 'page', 'pagesize') == false) rsp_die_json(10001, '参数缺失');
            $page = (max(1, $params['page']) - 1) * $params['pagesize'];
            //查询商户信息
            $merchant_info = $this->merchant();
            $data = [
                'pano' => $merchant_info['pano'],
                'atid' => $merchant_info['atid'],
                'ano' => $params['cano'],
                'skip' => $page,
                'limit' => $params['pagesize'],
                'starttime' => strtotime('-6months'), //默认查询半年
                'stoptime' => time()
            ];

            if (isTrueKey($params, 'time_begin')) $data['starttime'] = strtotime($params['time_begin']);
            if (isTrueKey($params, 'time_end')) $data['stoptime'] = strtotime($params['time_end']);
            if (isset($params['ispay']) && in_array($params['ispay'], [0, 1, 2])) $data['ispay'] = $params['ispay'];
            $result = $this->integral->post('/transaction', array_merge(['action' => 'lists'], $data));
            if (!$result || $result['code'] != 0) rsp_success_json(['total' => 0, 'lists' => []], 'success');
            $data['skip'] = 0;
            $data['limit'] = 9999; //积分系统不支持查所有  固定页码值
            $count = $this->integral->post('/transaction', array_merge(['action' => 'lists'], $data));

            $total = 0;
            if ($count && $count['code'] == 0 && $count['content']) {
                $total = count($count['content']);
            }
            rsp_success_json(['total' => $total, 'lists' => $result['content']], 'success');
        } catch (\Exception $e) {
            rsp_die_json(10004, $e->getMessage());
        }
    }

    /**
     * 交易 转账 消费
     * @param array $params
     */
    public function fasttransaction($params = [])
    {
        try {
            log_message(__METHOD__ . '-----params=' . json_encode($params));
            if (isTrueKey($params, 'destaccountno', 'orgaccountno', 'money', 'code', 'org_mobile') == false) rsp_die_json(10001, '参数缺失');
            $key = 'smscode_integral' . '_' . $params['org_mobile'];
            $code = Comm_Redis::getInstance()->get($key);
            if (isTrueKey($params, 'code') == false || $params['code'] != $code) {
                rsp_die_json(10011, '短信验证码错误');
            }
            //查询商户信息
            $merchant_info = $this->merchant();
            //查询账号信息
            $canos = [$params['destaccountno'], $params['orgaccountno']];
            $tmp = $this->integral->post('/merchant/accountLists', ['canos' => $canos]);
            if ($tmp['code'] != 0 || empty($tmp['content'])) rsp_die_json(10002, '账号信息不存在');
            $account_info = many_array_column($tmp['content'], 'cano');
            $utype = $account_info[$params['destaccountno']]['utype'];
            //收款人手机号码
            $des_mobile = $account_info[$params['destaccountno']]['identification'];
            $money = $merchant_info['blance_to_rmb'] * $params['money'];
            if ($utype == 'client') {
                $length = 5;
                $mobile = substr_replace($params['org_mobile'], str_repeat("*", $length), 3, $length);
                $des_mobile = substr_replace($des_mobile, str_repeat("*", $length), 3, $length);
                $detail = '收到' . $mobile . '积分转账' . $money;
                $content = '向' . $des_mobile . '积分转账' . $money;
            } elseif ($utype == 'business') {
                $detail = '收到商户积分消费' . $money;
                $content = '向商户积分消费' . $money;
            } else {
                rsp_die_json(10002, '收款方类型错误');
            }
            $tnum = order_sn();
            if (!$tnum) rsp_die_json(10002, '订单号生成失败');
            $data = [
                'destaccountno' => $params['destaccountno'],
                'orgaccountno' => $params['orgaccountno'],
                'money' => $money,
                'content' => $content,
                'detail' => $detail,
                'orderno' => $tnum,
                'orgtype' => $merchant_info['atid'],
                'desttype' => $merchant_info['atid'],
            ];
            $result = $this->integral->post('/transaction', array_merge(['action' => 'fastTransaction'], $data));
            if ($result['code'] != 0 || !$result['content']) rsp_die_json(10002, $result['message']);
            rsp_success_json(['tnum' => $result['content']['tno']], 'success');
        } catch (\Exception $e) {
            rsp_die_json(10004, $e - getMessage());
        }
    }

    /**
     * 订单详情
     * @param array $params
     */
    public function detail($params = [])
    {
        try {
            log_message(__METHOD__ . '-----params=' . json_encode($params));
            if (isTrueKey($params, 'tnum') === false) rsp_die_json(10001, '参数缺失');
            //查询子商户所对应的币种
            $merchant_info = $this->merchant();
            $result = $this->integral->post('/transaction', array_merge(['action' => 'detail'], ['tno' => $params['tnum']]));
            if ($result['code'] != 0 || empty($result['content'])) rsp_success_json([], 'success');
            $result['content']['rmb_money'] = bcdiv($result['content']['money'], $merchant_info['blance_to_rmb'], 2);
            rsp_success_json($result['content'], 'success');
        } catch (\Exception $e) {
            rsp_success_json(10004, $e->getMessage());
        }
    }

    /**
     * 短信验证码
     * @param array $post
     *
     */
    public function smsCaptcha($post = [])
    {
        if (isTrueKey($post, 'mobile') == false || !isMobile($post['mobile'])) {
            rsp_die_json(10001, '手机号不能为空');
        }
        if (isTrueKey($post, 'source', 'mobile') == false) {
            rsp_die_json(10001, '参数缺失');
        }
        $redis = Comm_Redis::getInstance();
        $source = "smscode_" . $post['source'];
        $key = $source . '_' . $post['mobile'];
        $code = mt_rand(10000, 99999);
        $redis->set($key, $code);
        $redis->expire($key, 300);
        $result = Comm_Sms::sendSms($post['mobile'], ['code' => $code, 'minute' => 5]);
        if ($result['code'] != 0) {
            rsp_die_json(10007, '发送失败');
        }
        rsp_success_json('', '发送成功');
    }
}