<?php

class Comm_Curl {

    private $cfg = [];
    private $service_addr = "";

    public function __construct($option = array()){
        $default = [ 'service'=>'etinout', 'format'=>'json','header'=>["Content-Type:application/x-www-form-urlencoded"]];  //默认配置
        if( empty($option) ){
            $this->cfg = $default;
        }else{
            $this->cfg = $option;
            if( !isset($option['service']) || check_empty($option['service']) ) $this->cfg['service'] = $default['service'];
            if( !isset($option['format']) || check_empty($option['format']) ) $this->cfg['format'] = $default['format'];
            if( !isset($option['header']) || check_empty($option['header']) ) $this->cfg['header'] = $default['header'];
        }

        $addr = $this->getAllServiceAddr();
        if( !check_empty($this->cfg['service']) && isset( $addr[$this->cfg['service']] ) && !check_empty( $addr[$this->cfg['service']] ) ){
            $this->service_addr = $addr[ $this->cfg['service'] ];
        }
        $this->checkServiceAddr($this->cfg['service']);
    }

    public function getCfg(){
        return $this->cfg;
    }

    // service 为空时 get put post delete函数参数 url要写完整
    public function service($val){
        if( check_empty($val) ){
            $this->cfg['service'] = "";
            $this->service_addr = "";
        }else{
            $addr = $this->getAllServiceAddr();
            $this->cfg['service'] = $val;
            $this->service_addr = $addr[ $val ];
        }
        return $this;
    }

    public function header($data = array()){
        if(!empty($data)) $this->cfg['header'] = $data;
        return $this;
    }

    public function format($val){
        if( !check_empty($val) ) $this->cfg['format'] = $val;
        return $this;
    }

    public function get($url, $params = []){
        $link = $this->service_addr.$url;
        if ($params) $link .= '?' . http_build_query($params);
        $text = $this->curl_text('get', $link, $params, $this->cfg['header'],$url);
        return $this->marshal($text);
    }

    public function post($url, $params = [], $case = []){
        $link = $this->service_addr.$url;
        $text = $this->curl_text('post', $link, $params, $this->cfg['header'],$url);
        return $this->marshal($text);
    }

    public function put($url, $params = []){
        $link = $this->service_addr.$url;
        $text = $this->curl_text('put', $link, $params, $this->cfg['header'],$url);
        return $this->marshal($text);
    }

    public function delete($url, $params = []){
        $link = $this->service_addr.$url;
        $text = $this->curl_text('delete', $link, $params, $this->cfg['header'],$url);
        return $this->marshal($text);
    }

    private function getAllServiceAddr(){
        $config = getConfig('ms.ini');
        $data = array(
            'auth2' => $config->get('auth2.url'), //auth2.0
            'user' => $config->get('user.url'),     //用户微服务
            'resource' => $config->get('resource.url'),   //资源管理微服务
            'fileupload' => $config->get('fileupload.url'), //文件上传微服务
            'pm' => $config->get('pm.url'), //项目管理微服务
            'tag' => $config->get('tag.url'), //栏目微服务
            'addr' => $config->get('addr.url'),  //地址微服务
            'company' => $config->get('company.url'), //公司管理微服务
            'agreement' => $config->get('agreement.url'), //公司管理微服务,
            'adv' => $config->get('adv.url'), //公司管理微服务
            'access' => $config->get('access.url'), //权限微服务
            'car' => $config->get('car.url'), //车辆微服务
            'route' => $config->get('route.url'), //路由微服务
            'tips' => $config->get('tips.url'), //提示码微服务
            'adapter' => $config->get('adapter.url'), //适配器微服务
            'log' => $config->get('log.url'), //日志微服务
            'tiding' => $config->get('tiding.url'), //消息中心
            'wxtoken' => $config->get('wxtoken.url'), //微信token获取
            'order' => $config->get('order.url'), //微信token获取
            'sn' => $config->get('sn.url'), //微信token获取
            'integral' => $config->get('integral.url'), //积分适配器
            'device' => $config->get('device.url'), //设备微服务
            'face' => $config->get('face.url'), //人脸微服务
            'station_adapter' => $config->get('station_adapter.url'), //停车场适配器微服务
            'msg' => $config->get('msg.url'), // 推送微服务
            'wos' => $config->get('wos.url'), //工单微服务
            'event_trigger' => $config->get('event_trigger.url'), //事件触发器服务
            'billing' => $config->get('cost.url'), //计费服务
            'rule' => $config->get('rule.url'), //计费服务
            'jz' => $config->get('jz.url'), //极致适配器服务
            'contract' => $config->get('contract.url'), //月卡微服务
            'report' => $config->get('data_report.url'), //报表服务
            'lumenscript' => $config->get('lumenscript.url'), //脚本
            'a4wechat' => $config->get('a4wechat.url'), //微信适配器
        );
        return $data;
    }

    private function marshal($text){
        if( strtoupper($this->cfg['format']) == 'JSON' ){
            return json_decode($text,true);
        }
        if( strtoupper($this->cfg['format']) == 'TEXT' ){
            return $text;
        }
    }

    public function curl_text($type, $url, $params = [], $header = ["Accept-Charset: utf-8"],$timeout = 5){

        // todo header加入调用方信息
        $header[] = 'Oauth-App-Id:'.($_SESSION['oauth_app_id'] ?? 0);
        $header[] = 'Oauth-Subapp-Id:'.($_SESSION['oauth_subapp_id'] ?? 0);
        $permission_key = !empty($_GET['permissions_key'])?$_GET['permissions_key']:'';
        if(!empty($params['project_ids']) && 'all' == $params["project_ids"]){
            $params = (new AuthModel())->getPermissionProjects($permission_key,$params);
        }
        $header = array_merge($header,$this->getTracerHeaders());
        $type = strtoupper($type);
        if( in_array($type,['GET','POST','PUT','DELETE']) == false ) return false;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT,$timeout);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        if($type != 'GET' && $params){
            $data = is_array($params) ? http_build_query($params) : $params;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        set_log([
            'curl' => [
                'url' => $url,
                'method' => $type,
                'params' => $params,
                'header' => $header
            ]
        ]);
        $temp = curl_exec($ch);
        $err_no = curl_error($ch);
        if($err_no){
            $info  = curl_getinfo($ch);
            $info['errno'] = $err_no;
            $msg = "curlRemote : ".$url." - ".$type."\r\n";
            set_log(['curl_error' => $info]);
            log_message($msg.json_encode($info,JSON_UNESCAPED_UNICODE).json_encode($params,JSON_UNESCAPED_UNICODE)."\r\n\r\n");
            return false;
        }
        curl_close($ch);
        log_message($url." -- ".$type." -- params: ".json_encode($params).' header:'. json_encode($header));
        return $temp;
    }

    private function checkServiceAddr($service_name){
        if( !$this->service_addr ){
            throw new \Exception('The \''.$service_name.'\' service url is missing','101');
        }
    }

    /**
     * 获取jaeger headers
     * @return array
     */
    private function getTracerHeaders()
    {
        $headers = [];
        $span_info = Yaf_Registry::get('span_info');
        if ( $span_info && isset($span_info['x-b3-sampled']) && isset($span_info['x-b3-spanid']) &&
            isset($span_info['x-b3-parentspanid']) && isset($span_info['x-b3-traceid'])) {
            $headers = [
                'x-b3-sampled:'.$span_info['x-b3-sampled'],
                'x-b3-spanid:'.$span_info['x-b3-spanid'],
                'x-b3-parentspanid:'.$span_info['x-b3-parentspanid'],
                'x-b3-traceid:'.$span_info['x-b3-traceid'],
            ];
        }
        return $headers;
    }
}
