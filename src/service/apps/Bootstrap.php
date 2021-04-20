<?php
class Bootstrap extends Yaf_Bootstrap_Abstract{
    public function _initRegistry(){
//        Yaf_Registry::set('config', Yaf_Application::app()->getConfig());
    }

    public function _initPlugin(Yaf_Dispatcher $dispatcher){
        $event = new EventPlugin();
        $dispatcher->registerPlugin($event);
    }

    public function _initError(Yaf_Dispatcher $dispatcher){
        $handle = function ($errno, $errstr, $errfile, $errline){
            $info = [
                'trace_id'=>'',
                'code'=>500,
                'message'=>'语法错误',
                'content'=>['errno'=>$errno,'errstr'=>$errstr,'errfile'=>$errfile,'errline'=>$errline]
            ];

            log_message("----500--error--".json_encode($info));

            $GLOBALS['request_info']['error_info'] = $info;
            $span_info = Yaf_Registry::get('span_info');
            if( $span_info && isset($span_info['x-b3-traceid']) ){
                $info['trace_id'] = $span_info['x-b3-traceid'];
            }
            set_log(['error'=>$info]);
            rsp_setting(json_encode($info));
            return true;
        };
        function my_exception ($e){
            $info = [
                'trace_id'=>'',
                'code'=>500,
                'message'=>'网络繁忙',
                'content'=>[
                    'errno'=>$e->getCode(),
                    'errmsg'=>$e->getMessage(),
                    'errfile'=>$e->getFile(),
                    'errline'=>$e->getLine()
                ]
            ];
            log_message("----500--error--".json_encode($info));
            $GLOBALS['request_info']['error_info'] = $info;
            $span_info = Yaf_Registry::get('span_info');
            if( $span_info && isset($span_info['x-b3-traceid']) ){
                $info['trace_id'] = $span_info['x-b3-traceid'];
            }
            set_log(['error'=>$info]);
            rsp_setting(json_encode($info));
            return true;
        }
        set_exception_handler("my_exception");
        $dispatcher->setErrorHandler($handle);
    }

    public function _initRoute(Yaf_Dispatcher $dispatcher) {
        $method = $dispatcher->getRequest()->getMethod();  //GET,POST,HEAD,PUT,DELETE,CLI
        $router = $dispatcher->getRouter();

        $simple = new Yaf_Route_Simple("m", "c", "a");
        $router->addRoute("simple", $simple);

        $super = new Yaf_Route_Supervar("r");
        $router->addRoute("supervar", $super);

        $map = require_once(CONFIG_PATH ."/router.php");
        $cfg = array_filter($map,function($k) use($method){
            return isset($k['route']['method']) && strtoupper($k['route']['method']) == strtoupper($method);
        });
        if( $cfg && !empty($cfg) ){
            $r = new Yaf_Config_Simple($cfg);
            $router->addConfig($r);
        }
    }

    public function _initSession(){
        $cfg = getConfig('redis.ini');

        $lifeTime = $cfg->session->lifeTime ?? 86400;  //session 有效期 1天

        @ini_set('session.gc_maxlifetime',$lifeTime);

        if( isset($cfg->session) && isset($cfg->session->handle) && strtolower($cfg->session->handle) == 'redis' ){
            $redisCof = $cfg->redis->default ?? [];
            @ini_set("session.save_handler", "redis");
            if( isset($redisCof->auth) && $redisCof->auth ){
                @ini_set(
                    "session.save_path",
                    "tcp://".$redisCof->host.":".$redisCof->port."?auth=".$redisCof->auth
                );
            }else{
                @ini_set("session.save_path", "tcp://".$redisCof->host.":".$redisCof->port);
            }
        }
        $token = ( !check_empty($GLOBALS["_POST"]) && isset($GLOBALS["_POST"]["token"]) && !check_empty($GLOBALS["_POST"]["token"]) ) ? $GLOBALS["_POST"]["token"] : false;
        if( $token && preg_match("/^[0-9a-zA-Z\-\,]+(,[0-9a-zA-Z\-\,]+)*$/",$token) ){
            @session_id($token);
            @session_start([ 'cookie_lifetime' => $lifeTime ]);
        }
    }

}


?>
