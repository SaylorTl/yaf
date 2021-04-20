<?php

class  IndexController extends Yaf_Controller_Abstract
{

    public function init()
    {
        Yaf_Dispatcher::getInstance()->disableView();
    }

    public function indexAction()
    {
        die("index/index/index");
    }

    public function testAction()
    {
        $cfg = getConfig('other.ini');
        $limitHost = $cfg->get('wxaccess.limitHost');
        if ($_SERVER['HTTP_HOST'] != $limitHost) {
            rsp_die_json(10001, '无权限获取微信鉴权信息');
        }

        $query = $this->getRequest()->getQuery();
        $params = $this->getRequest()->getParams();

        $path = $params['path'] ?? 'access_token';
        if( isTrueKey($query,'path') ){
            $path = $query['path'];
        }

        $uri = "/{$path}?" . http_build_query($query);
        $txt = (new Comm_Curl(['service' => 'wxtoken', 'format' => 'TEXT']))->get($uri, []);
        rsp_setting($txt);
    }

    public function appidAction()
    {
        $query = $this->getRequest()->getQuery();
        $format = isTrueKey($query, 'format') ? $query['format'] : 'javascript';
        $type = isset($query['type']) ? $query['type'] : 1;

        $data = [
            '1' => ['app_id' => 'AsHA1bBUts07', 'app_name' => '智慧社区'],
            '2' => ['app_id' => 'GjVbGM7jnS5g', 'app_name' => '正嘉杰'],
            '3' => ['app_id' => 'qM8zo860aYd0', 'app_name' => '印象物业'],
            '4' => ['app_id' => 'RLJeQtRMg4Kx', 'app_name' => '恒达物业'],
            '5' => ['app_id' => 'TunpTBxGH7Pw', 'app_name' => '茗邦物业'],
            '6' => ['app_id' => 'V2pSB8aC3IJ9', 'app_name' => '中惠宜家'],
        ];

        $result = [];
        if ($type == 1) {
            foreach ($data as $k => $v) {
                $result[$v['app_id']] = $v['app_name'];
            }
        } elseif ($type == 2) {
            $result = $data;
        }

        $response = new Yaf_Response_Http();
        $txt = "";
        if ($format == 'javascript') {
            $txt = "var __appid_cfg = " . json_encode($result, JSON_UNESCAPED_UNICODE) . "; \n";
            $txt .= "window.appid = __appid_cfg;\n";
            $response->setHeader('Content-Type', 'application/javascript;charset=utf-8');
        } else {
            $txt = json_encode($result, JSON_UNESCAPED_UNICODE);
            $response->setHeader('Content-Type', 'application/json;charset=utf-8');
        }
        $response->setBody($txt);
        $response->response();
        die();
    }

}

?>
