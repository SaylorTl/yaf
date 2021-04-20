<?php

/**
 * 公众号消息/事件处理
 */

use Wechat\ConstantModel as Constant;
use Wechat\MsgModel as MsgModel;

class WechatmsgController extends Yaf_Controller_Abstract
{
    protected $redis;

    protected $wx_token_service;

    protected $msg;

    protected $wx_appid;

    protected $wx_secret;

    protected $short_tnum;

    protected $token;

    protected $wx_encodingaeskey;

    protected $info;

    public function init()
    {
        $appid = array_values(array_filter(explode('/', $_SERVER['REQUEST_URI'])))[0];
        $config = getConfig('wechat.ini');
        $this->wx_appid = $config->wechat->$appid ? $appid : $config->wechat->appid;
        $this->wx_secret = $config->wechat->$appid ? $config->wechat->$appid->secret : $config->wechat->secret;
        $this->short_tnum = 'OPENTM418215377';
        $this->wx_encodingaeskey = $config->wechat->encodingaeskey;
        $this->token = $config->wechat->token;
        $this->redis = Comm_Redis::getInstance();
        $this->wx_token_service = new Comm_Curl(['service' => 'wxtoken', 'format' => 'json']);
        $this->msg = new Comm_Curl(['service' => 'msg', 'format' => 'json']);
    }

    public function indexAction()
    {
        $query = $this->getRequest()->getQuery();
        info(__METHOD__, ['query' => $query]);

        // 服务器验证
        if (isTrueKey($query, ...['signature', 'timestamp', 'nonce', 'echostr'])) {
            $result = $this->checkSignature($query['signature'], $query['timestamp'], $query['nonce'], $this->token);
            if ($result) exit($query['echostr']);
            exit('服务器验证失败');
        }

        $xml = file_get_contents("php://input");
        // 安全模式解密
        if (isTrueKey($query, 'msg_signature')) $xml = $this->decryptXml($query['timestamp'], $xml, $query['nonce'], $query['msg_signature']);
        $this->info = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        info(__METHOD__, ['info' => $this->info]);
        $get_method = $this->info['MsgType'];
        if (strtolower($this->info['MsgType']) !== 'event' && !method_exists($this, $get_method)) {
            $this->reply();
        }

        $method = strtolower($this->info['Event'] ?? $get_method);
        if (!method_exists($this, $method)) $this->reply();
        $this->$method();
    }

    /**
     * 被动回复用户消息-处理文本消息
     */
    private function text()
    {
        $content = $this->info['Content'] ?? '';
        $data = $this->redis->hget(Constant::REDIS_KEY['wx_msg_to_user'], $this->wx_appid . '_' . $content);
        info(__METHOD__, ['redis_result' => $data]);
        $data = empty($data) ? [] : json_decode($data, true);
        if (empty($data) || !isTrueKey($data, 'MsgType')) {
            $this->reply();
        }
        $this->replyParams($data);
    }

    /**
     * 关注
     */
    private function subscribe()
    {
        $this->updateClient();
        $ticket = $this->info['Ticket'] ?? '';
        if (!$ticket) $this->reply('你好，欢迎关注');
        $params = $this->redis->get(Constant::REDIS_KEY['qrcode_temp'] . $ticket);
        if (!$params) $params = $this->redis->hget(Constant::REDIS_KEY['qrcode_forever'], $ticket);
        if (!$params) $this->reply('你好，欢迎关注');
        $params = json_decode($params, true);

        $wx_info = $this->wx_token_service->get('/wxinfo', ['openid' => $this->info['FromUserName'], 'app_id' => $this->wx_appid]);
        $wx_info = $wx_info['content'] ?? [];
        $nickname = $wx_info['nickname'] ?? '';
        $post = [
            'channel' => ['wechat'],
            'title' => '关注回复',
            'source' => 'subscribe_reply',
            'open_id' => $this->info['FromUserName'],
            'third_app_id' => $this->wx_appid,
            'short_tnum' => $this->short_tnum,
            'wx_url' => $params['url'] ?? '',
            'wx_params' => json_encode([
                'first' => [
                    'value' => "你的微信账号{$nickname}即将进行网站登录操作",
                    'color' => '#173177',
                ],
                'keyword1' => [
                    'value' => $wx_info['nickname'] ?? '',
                    'color' => '#173177',
                ],
                'keyword2' => [
                    'value' => $params['p'] ?? '',
                    'color' => '#173177',
                ],
                'keyword3' => [
                    'value' => date('Y年m月d日 H:i:s'),
                    'color' => '#173177',
                ],
                'remark' => [
                    'value' => '请点击确认登录，谢谢',
                    'color' => '#173177',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        $sent = $this->msg->post('/pushmsg/singleUser', $post);
        info(__METHOD__, ['pushmsg' => $post, 'sent' => $sent]);
        $this->reply();
    }

    /**
     * 取消关注
     */
    private function unsubscribe()
    {
        $this->updateClient();
        $this->reply();
    }

    /**
     * 扫码
     */
    private function scan()
    {
        $this->subscribe();
    }

    /*--------------------------------------------    click event  ---------------------------------------------------*/
    /**
     * 自定义点击菜单
     */
    private function click()
    {
        $event_key = strtolower($this->info['EventKey'] ?? '');
        if (!$event_key) $this->reply();
        if (!method_exists($this, $event_key)) $this->reply();
        $this->$event_key();
    }

    private function customer_service()
    {
        $this->reply();
    }

    private function coming_soon()
    {
        $content = '敬请期待';
        $this->reply($content);
    }

    /*-----------------------------------------------    util  -------------------------------------------------------*/
    private function replyParams($params)
    {
        $MsgType = $params['MsgType'] ?? '';
        $xml = '<xml>';
        $xml .= '<ToUserName><![CDATA[' . $this->info['FromUserName'] . ']]></ToUserName>';
        $xml .= '<FromUserName><![CDATA[' . $this->info['ToUserName'] . ']]></FromUserName>';
        $xml .= '<CreateTime>' . time() . '</CreateTime>';
        $xml .= '<MsgType><![CDATA[' . $MsgType . ']]></MsgType>';
        $obj = (new MsgModel());
        if (method_exists($obj, $MsgType)) {
            $xml .= $obj->$MsgType($params);
            info(__METHOD__, ['MsgType' => $MsgType, 'xml' => $xml]);
        }
        $xml .= '</xml>';
        exit($xml);
    }

    private function reply($content = '')
    {
        if (!$content) exit('');
        $xml = '<xml>';
        $xml .= '<ToUserName><![CDATA[' . $this->info['FromUserName'] . ']]></ToUserName>';
        $xml .= '<FromUserName><![CDATA[' . $this->info['ToUserName'] . ']]></FromUserName>';
        $xml .= '<CreateTime>' . time() . '</CreateTime>';
        $xml .= '<MsgType><![CDATA[text]]></MsgType>';
        $xml .= '<Content><![CDATA[' . $content . ']]></Content>';
        $xml .= '</xml>';
        exit($xml);
    }

    /**
     * 微信第一次接入token校验
     * @param $signature
     * @param $timestamp
     * @param $nonce
     * @param $token
     * @return bool
     */
    private function checkSignature($signature, $timestamp, $nonce, $token)
    {
        $arr = [$token, $timestamp, $nonce];
        sort($arr, SORT_STRING);
        $sign = sha1(implode($arr));
        return $sign === $signature;
    }

    private function getSign($data)
    {
        ksort($data);
        $str = "";
        foreach ($data as $k => $v) {
            if ($k === 'attach') continue;
            $str .= ($str === "" ? "" : "&") . strtolower($k) . "=" . $v;
        }
        return hash('sha256', $str, false);
    }

    private function decryptXml($timeStamp, $xml, $nonce, $msg_sign)
    {
        try {
            $msg = '';
            $pc = new \Wechat_MsgCrypt($this->token, $this->wx_encodingaeskey, $this->wx_appid);
            $errCode = $pc->decryptMsg($msg_sign, $timeStamp, $nonce, $xml, $msg);
            if ($errCode == 0) {
                return $msg;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    private function encryptXml($timeStamp, $xml, $nonce)
    {
        $pc = new \Wechat_MsgCrypt($this->token, $this->wx_encodingaeskey, $this->wx_appid);
        $encryptMsg = '';
        $errCode = $pc->encryptMsg($xml, $timeStamp, $nonce, $encryptMsg);
        if ($errCode == 0) {
            return $encryptMsg;
        } else {
            return false;
        }
    }

    /**
     * 修改用户是否关注字段
     */
    private function updateClient()
    {
        try {
            $is_subscribe = strtolower($this->info['Event']) == 'subscribe' ? 'Y' : 'N';
            $user = new Comm_Curl(['service' => 'user', 'format' => 'json']);
            $result = $user->post('/client/update', [
                'openid' => $this->info['FromUserName'],
                'is_subscribe' => $is_subscribe
            ]);
            if (!$result || $result['code'] != 0) {
                log_message(__METHOD__ . '----修改用户失败：' . json_encode([$result]));
            }
        } catch (\Exception $e) {
            log_message(__METHOD__ . '---err_msg:' . $e->getMessage() . '----line:' . $e->getLine());
        }
    }

}