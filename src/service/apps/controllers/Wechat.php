<?php

use Wechat\ConstantModel as Constant;

class WechatController extends Yaf_Controller_Abstract
{
    protected $wx_appid;

    protected $wx_token;

    protected $redis;

	public function init()
    {
        Yaf_Dispatcher::getInstance()->disableView();

        $appid = array_values(array_filter(explode('/', $_SERVER['REQUEST_URI'])))[0];
        $config = getConfig('wechat.ini');
        $this->wx_appid = $config->wechat->$appid ? $appid : $config->wechat->appid;
        if (!$this->setWxToken()) {
            rsp_die_json(10001, '获取微信token失败');
        }
        $this->redis = Comm_Redis::getInstance();
	}

    /**
     * 设置公众号菜单
     */
	public function menuSetAction()
    {
        $post = $this->getRequest()->getPost();
        if (!isTrueKey($post, ...['token', 'menu'])) rsp_error_tips(10001);
        $res = curl_text('post', "https://api.weixin.qq.com/cgi-bin/menu/create?access_token={$post['token']}", $post['menu']);
        $res = json_decode($res, true);
        if ($res['errcode'] === 0 ) rsp_success_json($res['errmsg']);
        rsp_die_json(10002, $res['errmsg']);
    }

    /**
     * 生成临时二维码
     */
	public function createTempQrcodeAction()
    {
		$request = $this->getRequest();
		if($request->getMethod() != 'GET') rsp_die_json(10001,'请求错误');
		$query = $request->getQuery();

		$ex = $query['ex'] ?? 600; //有效期默认10分钟
        unset($query['r'], $query['ex']);
		$params = $query;
		$rand_id = $this->getRand();
		$action_name = 'QR_SCENE';
		$ticket = $this->getTicket( $this->wx_token, $rand_id, $action_name, $ex);
		if($ticket === false) rsp_die_json(10003,'ticket获取失败');
		$this->redis->setEX(Constant::REDIS_KEY['qrcode_temp'].$ticket, $ex + 5, json_encode($params));

		$url = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".urlencode($ticket);
        info(__METHOD__, ['url' => $url, 'query' => $query]);
		$img = curl_text('GET', $url);
		header('Content-type: image/jpg');
		echo $img;
		exit;
	}

    /**
     * 生成永久二维码
     */
	public function createForeverQrcodeAction()
    {
		$request = $this->getRequest();
		if($request->getMethod() != 'GET') rsp_die_json(10001,'请求错误');
		$query = $request->getQuery();

        unset($query['r']);
        $params = $query;

		$rand_id = rand(1,100000);
		$action_name = 'QR_LIMIT_SCENE'; //永久二维码
		$ticket = $this->getTicket( $this->wx_token,$rand_id,$action_name );
		if($ticket === false) rsp_die_json(10003,'ticket获取失败');

		$this->redis->hset(Constant::REDIS_KEY['qrcode_forever'], $ticket, json_encode($params));

		$url = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".urlencode($ticket);
        info(__METHOD__, ['url' => $url, 'query' => $query]);
		$img = curl_text('GET', $url);
		header('Content-type: image/jpg');
		echo $img;
		exit;
	}

    /**
     * 设置token
     * @return bool
     */
    private function setWxToken()
    {
        $tmp = (new Comm_Curl(['service' => 'wxtoken', 'format' => 'json']))->get('/access_token', ['app_id' => $this->wx_appid]);
        info(__METHOD__, $tmp);
        if((int)$tmp['code'] !== 0) return false;
        $this->wx_token = $tmp['content'];
        return true;
    }

    /**
     * 获取ticket
     * @param $token
     * @param $rand_id
     * @param string $action_name
     * @param string $expire_seconds
     * @return bool
     */
	private function getTicket($token, $rand_id, $action_name = '', $expire_seconds = '')
    {
		$url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=".$token;
		$create_params = [];
		if( !empty($expire_seconds) ) $create_params['expire_seconds'] = $expire_seconds;
		if( !empty($action_name) ) $create_params['action_name'] = $action_name;
		$create_params['action_info'] = ['scene'=>['scene_id'=>$rand_id] ] ;
		$tmp = curl_text('POST',$url, json_encode($create_params), ['content-type:application/json']);
		$tmp = json_decode($tmp,true);
        info(__METHOD__, ['res' => $tmp, 'params' => $create_params, 'url' => $url]);
		if(!isset($tmp['ticket']) || empty($tmp['ticket']) ) return false;
		return $tmp['ticket'];
	}

	private function getRand(){
		return date('YmdHis').time().rand(10000000,99999999);
	}
}