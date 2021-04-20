<?php

use Jaeger\Span;

class Tool_YafTracer
{

    /**
     * 获取功能开关
     */
    private static function getSwitch()
    {
        $config = Yaf_Registry::get('config');
        if (!$config->jaeger) {
            log_message('----Tool_JaegerTracer/'.__FUNCTION__.'----,'.json_encode([
                    'line' => __LINE__,
                    'error' => 'jaegertracing配置项缺失'
                ], JSON_UNESCAPED_UNICODE));
            return false;
        }
        $switch = $config->jaeger->switch;
        if ($switch == 'on') {
            return true;
        }
        return false;
    }

    /**
     * @param  Yaf_Request_Abstract  $request
     * @return array
     */
    public static function getRequestParams(Yaf_Request_Abstract $request)
    {
        $params = [];
        if ($request->isGet() || $request->isPut() || $request->isDelete()) {
            $params = $request->getQuery();
        } elseif ($request->isPost()) {
            $params = $request->getPost();
        }
        return $params;
    }

    /**
     * 设置Tracer全局变量
     */
    public static function setTracer()
    {
        //获取功能开关，关闭时直接返回false
        if (!self::getSwitch()) {
            return false;
        }
        try {
            $tracer = Tool_JaegerTracer::getInstance();
        } catch (\Exception $e) {
            log_message('----Tool_JaegerTracer/'.__FUNCTION__.'----,'.json_encode([
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'error' => $e->getMessage(),
                ]));
            return false;
        }
        if ($tracer instanceof Tool_JaegerTracer) {
            Yaf_Registry::set('tracer', $tracer);
            return true;
        } else {
            log_message('----Tool_JaegerTracer/'.__FUNCTION__.'----,'.json_encode([
                    'line' => __LINE__,
                    'error' => 'tracer is not an Tool_JaegerTracer object'
                ]));
            return false;
        }
    }

    /**
     * 设置请求的span
     * @param  Yaf_Request_Abstract  $request  请求实例
     * @param  array  $inject_target  上一个节点的信息
     * @return bool
     * @throws Exception
     */
    public static function setRequestSpan(Yaf_Request_Abstract $request, $inject_target = [])
    {
        //获取功能开关，关闭时直接返回false
        if (!self::getSwitch()) {
            return false;
        }
        $params = self::getRequestParams($request);
        $uri = $request->getRequestUri();
        if ($uri == '/gateway' && isset($params['method']) && $params['method']) {
            $span_name = $params['method'];
        } else {
            $span_name = $uri;
        }
        return self::setSpan($span_name, $params, $inject_target);
    }

    /**
     * 设置span
     * @param  string  $span_name  span名称
     * @param  array  $params  span日志信息
     * @param  array  $inject_target
     * @throws Exception
     */
    public static function setSpan($span_name, $params, $inject_target = [])
    {
        //获取功能开关，关闭时直接返回false
        if (!self::getSwitch()) {
            return false;
        }
        if (self::checkTracer() == false) {
            return false;
        }
        $tracer = Yaf_Registry::get('tracer');
        try {
            $span = $tracer::getSpan($span_name, $inject_target);
            $span_info = $tracer::getSpanInfo($span);
            //记录请求参数信息
            $span->log($params);
            //设置/更新全局变量
            Yaf_Registry::set('span', $span);
            Yaf_Registry::set('span_info', $span_info);
            return true;
        } catch (\Exception $e) {
            log_message('----Tool_JaegerTracer/'.__FUNCTION__.'----,'.json_encode([
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'error' => $e->getMessage(),
                ]));
            return false;
        }
    }

    /**
     * 设置span的信息
     * @return bool
     * @throws Exception
     */
    public static function setSpanInfo()
    {
        //获取功能开关，关闭时直接返回false
        if (!self::getSwitch()) {
            return false;
        }
        if (!self::checkTracer() || !self::checkSpan()) {
            return false;
        }
        $tracer = Yaf_Registry::get('tracer');
        $span = Yaf_Registry::get('span');
        try {
            $span_info = $tracer::getSpanInfo($span);
        } catch (\Exception $e) {
            log_message('----Tool_JaegerTracer/'.__FUNCTION__.'----,'.json_encode([
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'error' => $e->getMessage(),
                ]));
            return false;
        }
        Yaf_Registry::set('span_info', $span_info);
        return true;
    }

    /**
     * 完成当前节点
     * @throws Exception
     */
    public static function finish()
    {
        if (self::checkSpan()) {
            Yaf_Registry::get('span')->finish();
        }
    }

    /**
     * 推送信息到jaegertracing
     */
    public static function flush()
    {
        if (self::checkTracer()) {
            Yaf_Registry::get('tracer')->flush();
        }
    }

    /**
     * 检查span对象
     * @return bool
     */
    public static function checkSpan()
    {
        //获取功能开关，关闭时直接返回false
        if (!self::getSwitch()) {
            return false;
        }
        $span = Yaf_Registry::get('span');
        if (!($span instanceof Span)) {
            log_message('----Tool_JaegerTracer/'.__FUNCTION__.'----,'.json_encode([
                    'line' => __LINE__,
                    'error' => 'span is not an \Jaeger\Span object'
                ]));
            return false;
        }
        return true;
    }

    /**
     * 检查tracer对象
     * @return bool
     */
    public static function checkTracer()
    {
        if (!self::getSwitch()) {
            return false;
        }
        $tracer = Yaf_Registry::get('tracer');
        if (!($tracer instanceof Tool_JaegerTracer)) {
            log_message('----Tool_JaegerTracer/'.__FUNCTION__.'----,'.json_encode([
                    'line' => __LINE__,
                    'error' => 'tracer is not an Tool_JaegerTracer object'
                ]));
            return false;
        }
        return true;
    }

    public static function getJaegerHeaders()
    {
        $headers = [];
        if (!self::getSwitch() || strpos(PHP_SAPI, 'cli') !== false) {
            return $headers;
        }
        foreach (getallheaders() as $key => $value) {
            $headers[strtolower($key)] = $value;
        }
        $inject_target = [];
        if (isset($headers['x-b3-sampled']) && isset($headers['x-b3-spanid']) &&
            isset($headers['x-b3-parentspanid']) && isset($headers['x-b3-traceid'])) {
            $inject_target = [
                'x-b3-sampled' => $headers['x-b3-sampled'],
                'x-b3-spanid' => $headers['x-b3-spanid'],
                'x-b3-parentspanid' => $headers['x-b3-parentspanid'],
                'x-b3-traceid' => $headers['x-b3-traceid'],
            ];
        }
        return $inject_target;
    }
}