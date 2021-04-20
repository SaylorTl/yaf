<?php

/**
 * jaegertracing接入测试
 * Class JaegerTestController
 */
class JaegerTestController extends Yaf_Controller_Abstract
{

    public function init()
    {
        Yaf_Dispatcher::getInstance()->disableView();
    }

    public function pmAction()
    {
        $curl = new Comm_Curl(['service' => 'pm', 'format' => 'json']);
        $result = $curl->get('/test', ['a' => 1, 'b' => 2]);
        rsp_success_json($result['content']);
    }

    public function userAction()
    {
        $curl = new Comm_Curl(['service' => 'user', 'format' => 'json']);
        $result = $curl->get('/test', ['a' => 1, 'b' => 2]);
        rsp_success_json($result['content']);
    }

    public function accessAction()
    {
        $curl = new Comm_Curl(['service' => 'access', 'format' => 'json']);
        $result = $curl->get('/test', ['a' => 1, 'b' => 2]);
        rsp_success_json($result['content']);
    }

    public function wxtokenAction()
    {
        $curl = new Comm_Curl(['service' => 'wxtoken', 'format' => 'json']);
        $result = $curl->get('/test', ['a' => 1, 'b' => 2]);
        rsp_success_json($result['content']);
    }
}

?>
