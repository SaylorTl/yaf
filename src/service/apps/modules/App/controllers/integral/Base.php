<?php

class Base
{

    protected $integral;
    protected $pm;
    protected $user;

    public function __construct()
    {
        $this->user = new Comm_Curl(['service' => 'user', 'format' => 'json']);
        $this->integral = new Comm_Curl(['service' => 'integral', 'format' => 'json']);
        $this->pm = new Comm_Curl(['service' => 'pm', 'format' => 'json']);
    }

    public function user($user_id)
    {
        $user_show = $this->user->post('/user/show', ['user_id' => $user_id]);
        if ($user_show['code'] != 0 || empty($user_show['content'])) throw new \Exception($user_show['message']);
        return $user_show['content'];
    }

    public function merchant()
    {
        $merchant_show = $this->integral->post('/merchant/show', []);
        log_message('币种商户查询结果=====' . json_encode($merchant_show));
        if ($merchant_show['code'] != 0 || !$merchant_show['content']) throw new \Exception('子商户币种信息不存在');
        return $merchant_show['content'];
    }

    public function account_show($m_id, $pano, $mobile, $nickname)
    {
        $account_show = $this->integral->post('/merchant/accountShow', [
            'm_id' => $m_id,
            'identification' => $mobile
        ]);
        if ($account_show['code'] != 0 || !$account_show['content']) {
            $data = [
                'pano' => $pano,
                'mode' => 0,
                'mobile' => $mobile,
                'name' => $nickname,
            ];
            //往积分系统开通账号
            $result = $this->integral->post('/account', array_merge(['action' => 'openClientAccount'], $data));
            if ($result['code'] != 0) throw new \Exception($result['message']);
            $cano = $result['content']['account']['cano'];
            $account_params = [
                'm_id' => $m_id,
                'cano' => $cano,
                'utype' => 'client',
                'identification' => $mobile,
            ];
            $this->integral->post('/merchant/accountAdd', $account_params);
            $data = ['cano' => $cano, 'utype' => 'client'];
        } else {
            $data = ['cano' => $account_show['content']['cano'], 'utype' => $account_show['content']['cano']];
        }
        return $data;
    }
}