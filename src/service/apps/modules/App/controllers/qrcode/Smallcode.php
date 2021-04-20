<?php

class Smallcode extends Base
{

    public function unlimit_qrcode($params = [])
    {
        try {
            log_message('小程序码-----参数：' . json_encode($params));
            if (isTrueKey($params, 'business_id', 'app_id') == false) rsp_die_json(10002, 'business_id,app_id缺失');

            $params['app_id'] = 'wx1aad0c2e3c5d7ec4';
            $rsp = $this->token_url->get("/access_token?app_id=" . $params['app_id']);
//            $rsp = curl_json('GET', 'https://stage.test.sqygj.net/test?app_id=' . $params['app_id'], []);
            if ((int)$rsp['code'] !== 0) rsp_die_json(10001, $rsp['message']);
            $token = $rsp['content'];
            $req_params = [
                'scene' => $params['business_id'],
            ];
            if (isTrueKey($params, 'page')) $req_params['page'] = $params['page'];
            if (isTrueKey($params, 'width')) $req_params['width'] = $params['width'];
            if (isTrueKey($params, 'auto_color')) $req_params['auto_color'] = $params['auto_color'];
            if (isTrueKey($params, 'line_color')) $req_params['line_color'] = $params['line_color'];
            if (isTrueKey($params, 'is_hyaline')) $req_params['is_hyaline'] = $params['is_hyaline'];

            $rsp = curl_text('POST', 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=' . $token, json_encode($req_params));
            log_message('小程序码生成响应--------' . json_encode([$rsp]));
            if (strlen($rsp) <= 1000) $rsp = json_decode($rsp, true);

            if (isset($rsp['errcode']) && in_array((int)$rsp['errcode'], [48001, 47001, 45009, 41030])) rsp_die_json(10001, $rsp['errmsg']);
            rsp_success_json(['base64Img' => chunk_split(base64_encode($rsp))], 'success');
        } catch (\Exception $e) {
            rsp_die_json(10001, $e->getMessage());
        }
    }
}