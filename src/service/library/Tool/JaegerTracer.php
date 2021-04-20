<?php

use Jaeger\Config;
use OpenTracing\Span;
use const Jaeger\Constants\PROPAGATOR_ZIPKIN as PROPAGATOR_ZIPKINAlias;
use const OpenTracing\Formats\TEXT_MAP;

class Tool_JaegerTracer
{
    //服务名称
    private static $server_name = '';

    //jaeger代理地址和端口
    private static $agent_host_port = '';

    public static $tracer = null;

    public static $span = null;

    public static $instance = null;

    private function __construct()
    {

    }

    private function __clone()
    {

    }

    public static function getInstance()
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
            try {
                self::init();
            } catch (\Exception $e) {
                return false;
            }
        }
        return self::$instance;
    }

    /**
     * 初始化
     * @throws Exception
     */
    private static function init()
    {
        $config = Yaf_Registry::get('config');
        self::$server_name = $config->jaeger->server_name;
        self::$agent_host_port = $config->jaeger->agent_host_port;
        if (!self::$server_name) {
            throw new \Exception("jaeger.server_name undefined in application.ini file");
        }
        if (!self::$agent_host_port) {
            throw new \Exception("jaeger.agent_host_port undefined in application.ini file");
        }
        $config = Config::getInstance();
        $config->gen128bit();
        $config::$propagator = PROPAGATOR_ZIPKINAlias;
        self::$tracer = $config->initTracer(self::$server_name, self::$agent_host_port);
    }

    /**
     * 获取span
     * @param  string  $span_name
     * @param  array  $inject_target  上一个节点的信息
     * @return mixed
     * @throws Exception
     */
    public static function getSpan($span_name, $inject_target = [])
    {
        if ($span_name == '') {
            throw new \Exception("span_name require");
        }
        if (!empty($inject_target)) {
            $span_context = self::$tracer->extract(TEXT_MAP, $inject_target);
            $options = ['child_of' => $span_context];
        } else {
            $options = [];
        }
        return self::$tracer->startSpan($span_name, $options);
    }

    /**
     * 获取span的traceid、parentspanid、spanid和sampled等信息
     * @param  Span  $span
     * @return array
     * @throws Exception
     */
    public static function getSpanInfo($span)
    {
        if (!($span instanceof Span)) {
            throw new \Exception("It's not an \OpenTracing\Span object");
        }
        $info = [];
        self::$tracer->inject($span->spanContext, TEXT_MAP, $info);
        return $info;
    }

    /**
     * 推送信息到jaeger
     */
    public static function flush()
    {
        self::$tracer->flush();
    }
}