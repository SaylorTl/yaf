<?php

class EventPlugin extends Yaf_Plugin_Abstract
{

    //在路由之前触发
    public function routerStartup(Yaf_Request_Abstract $request, Yaf_Response_Abstract $response)
    {
        if (isTrueKey($_SERVER, 'REQUEST_METHOD') && strtoupper($_SERVER['REQUEST_METHOD']) == 'OPTIONS') {
            die(json_encode(['code' => 100, 'message' => 'options预检', 'content' => '']));
        }
        //从headers中获取上一个节点的信息
        $headers = Tool_YafTracer::getJaegerHeaders();
        //设置当前的span，并生成全局变量
        Tool_YafTracer::setRequestSpan($request, $headers);
        //记录请求头信息
        set_log(['Headers' => getallheaders()]);
    }


    //路由结束之后触发
    public function routerShutdown(Yaf_Request_Abstract $request, Yaf_Response_Abstract $response)
    {
    }

    //分发循环开始之前被触发
    public function dispatchLoopStartup(Yaf_Request_Abstract $request, Yaf_Response_Abstract $response)
    {
    }

    //分发之前触发
    public function preDispatch(Yaf_Request_Abstract $request, Yaf_Response_Abstract $response)
    {
    }

    //分发结束之后触发
    public function postDispatch(Yaf_Request_Abstract $request, Yaf_Response_Abstract $response)
    {
    }

    public function dispatchLoopShutdown(Yaf_Request_Abstract $request, Yaf_Response_Abstract $response)
    {
    }
}


?>
