<?php


class AuthController extends Yaf_Controller_Abstract
{
    /**
     * 绑定租户客户端信息
     */
    public function bindingClientAction()
    {
        $request = $this->getRequest();
        if ($request->getMethod() != 'GET') {
            die('请求错误');
        }
        $query = $request->getQuery();

        log_message(__FUNCTION__ . "【query】" . json_encode($query));
        if (!isTrueKey($query, 'business_tnum', 'code')) {
            die('缺少标识和授权码信息');
        }

        // 检测redis信息
        $redis = Comm_Redis::getInstance();
        $redis->select(8);
        $redisData = $redis->get('etbase_wechat_qr_ordernum:' . $query['business_tnum']);
        $redisData = $redisData ? json_decode($redisData, true) : [];
        log_message(__FUNCTION__ . "【redis】" . json_encode([$query, $redisData]));
        if (empty($redisData) || !isTrueKey($redisData, 'pay_url')) {
            die('授权信息不全');
        }
        if (!isTrueKey($redisData, 'third_party_app_id', 'auth_kind', 'oauth_app_id')) {
            header("Location:" . $redisData['pay_url']);
            exit();
        }

        $method = $redisData['auth_kind'];
        if (!method_exists($this, $method)) {
            header("Location:" . $redisData['pay_url']);
            exit();
        }
        $auth = $this->$method($redisData['third_party_app_id'], $query['code']);
        if ($auth['code'] != 0 || !isTrueKey($auth['content'], 'openid')) {
            header("Location:" . $redisData['pay_url']);
            exit();
        }

        // 绑定租户对应的用户信息
        $userId = $redisData['user_id'] ?? 0;
        $employeeId = $redisData['employee_id'] ?? 0;
        // 检测client信息
        $clientParams = [
            'app_id' => $redisData['third_party_app_id'],
            'openid' => $auth['content']['openid'],
            'client_app_id' => $redisData['oauth_app_id']
        ];
        $obj = new Comm_Curl(['service' => 'user']);
        $client = $obj->post('/client/show', $clientParams);

        $content = $client['content'] ?? [];
        $clientId = $content['client_id'] ?? 0;
        if (empty($content)) {
            $clientParams['user_id'] = $userId;
            $clientParams['employee_id'] = $employeeId;
            $clientParams['kind'] = strtoupper($method);
            $add = $obj->post('/client/add', $clientParams);
            $clientId = $add['content'] ?? 0;
        } else {
            $update = [
                'client_id' => $clientId,
                'user_id' => $content['user_id'] ?: $userId,
                'employee_id' => $content['employee_id'] ?: $employeeId,
            ];
            $obj->post('/client/update', $update);
        }
        log_message(__FUNCTION__ . "【clientId】" . json_encode([$query, $clientId]));

        // 修改订单的client_id
        if( $clientId ){
            $order_bind_Params = [
                'business_tnum' => $query['business_tnum'],
                'client_id' => $clientId,
                'sender_client_id' => $clientId
            ];
            $obj = new Comm_Curl(['service' => 'order', 'format' => 'json']);
            $updateResult = $obj->post('/order/update', $order_bind_Params);
            log_message(__FUNCTION__ . "【redis】" . json_encode([$order_bind_Params, $updateResult]));
        }

        header("Location:" . $redisData['pay_url']);
        exit();
    }

    /**
     * @param $appId
     * @param $code
     * @return array
     * 获取微信用户信息
     */
    protected function wechat($appId, $code)
    {
        // 从token微服务获取微信用户信息
        $tokenObj = new Comm_Curl(['service' => 'wxtoken']);
        $tmp = $tokenObj->get('/wxcode', ['app_id' => $appId, 'code' => $code]);
        log_message("【wxtoken】" . json_encode($tmp));

        if (empty($tmp) || isTrueKey($tmp, 'code') && $tmp['code'] != 0) {
            $code = in_array($tmp['code'], [40163, 40029]) ? $tmp['code'] : 10007;
            return $this->returnCode($code, "微信授权失败【" . $tmp['code'] . ":" . $tmp['message'] . "】");
        }
        if (false == isTrueKey($tmp['content'], 'openid', 'access_token')) {
            return $this->returnCode(10007, '微信授权请求结果不符合规范');
        }
        $tmp = $tmp['content'];

        // 获取微信token信息
        $wxInfo = $tokenObj->get("/wxinfo", ["openid" => $tmp['openid'], 'app_id' => $appId]);
        $wxInfo = $wxInfo['content'] ?? [];
        if (empty($wxInfo) || !is_array($wxInfo)) {
            return $this->returnCode(10007, '获取微信用户信息失败');
        }
        return $this->returnCode(0, '获取成功', $wxInfo);
    }

    protected function returnCode($code = 0, $msg = 'success', $data = '')
    {
        return ['code' => $code, 'message' => $msg, 'content' => $data];
    }
}