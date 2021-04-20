<?php

class Account extends Base
{

    public function openAndShow($params = [])
    {
        try {
            log_message(__METHOD__ . '----params=' . json_encode($params));
            if (isTrueKey($params, 'user_id', 'nickname') == false) rsp_die_json(10001, '参数缺失');
            //查询用户信息
            $user_info = $this->user($params['user_id']);
            //查询子商户所对应的币种
            $merchant_info = $this->merchant();
            //查询账号
            $info = $this->account_show(
                $merchant_info['m_id'],
                $merchant_info['pano'],
                $user_info['mobile'],
                $params['nickname']
            );
            if ($info['utype'] == 'client') {
                //查询用户余额
                $result = $this->integral->post('/account', array_merge(['action' => 'queryClientAccount'], [
                    'cano' => $info['cano'],
                    'pano' => $merchant_info['pano'],
                    'needSubAccountInfo' => ''
                ]));
            } else {
                //查询商户余额
                $result = $this->integral->post('/account', array_merge(['action' => 'queryBizAccount'], [
                    'bano' => $info['cano'],
                    'pano' => $merchant_info['pano'],
                ]));
            }
            if (!$result || $result['code'] != 0) rsp_die_json(10003, $result['message']);
            $result['content']['utype'] = $info['utype'];
            $result['content']['exchange_money'] = bcdiv($result['content']['money'], $merchant_info['blance_to_rmb'], 2);
            $result['content']['merchant_info'] = $merchant_info;
            rsp_success_json($result['content'], 'success');
        } catch (\Exception $e) {
            rsp_die_json(10004, $e->getMessage());
        }
    }
}
