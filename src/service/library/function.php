<?php

/**
 * @param $msg
 * @param int $type
 * 日志记录
 */
function log_message($msg,$filename = 'error'){
    $path = SERVICE_PATH."/data/logs/";
    if( is_dir($path) == false ) mkdir($path);
    $date = date('Y-m-d');

    $file = ERROR_LOG_FILE;
    if( $file != $path.$filename."-".$date.".log" ) $file = $path.$filename."-".$date.".log";

    if( @ini_get('error_log') != $file ) @ini_set('error_log',$file);
    $span_info = Yaf_Registry::get('span_info');
    if( $span_info && isset($span_info['x-b3-traceid']) ){
        $msg = 'traceid:'.$span_info['x-b3-traceid'].','.$msg;
    }
    error_log(date('Y-m-d H:i:s')."  ".$msg."\n", 3, $file);
}


/**
 * @param $value
 * @return bool
 * 检验是否为空
 */
function check_empty($value){
    if(!isset($value)) return true;
    if($value === null) return true;
    if(is_array($value) && empty($value) ) return true;
    if(is_string($value) && trim($value) === "") return true;
    return false;
}


function getip(){
    $unknown = 'unknown';
    $ip = "127.0.0.1";
    if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], $unknown)){
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], $unknown)) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    if (false !== strpos($ip, ',')) $ip = reset(explode(',', $ip));
    return $ip;
}

/**
 * @param $type
 * @param $url
 * @param array $params
 * @param array $header
 * @return bool|mixed
 * curl 请求
 */
function curl_text($type, $url, $params = [], $header = ["Accept-Charset: utf-8"],$timeout = 35){
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
    $temp = curl_exec($ch);
    $err_no = curl_error($ch);
    if($err_no){
        $info  = curl_getinfo($ch);
        $info['errno'] = $err_no;
        $msg = "curlRemote : ".$url." - ".$type."\r\n";
        log_message($msg.json_encode($info,JSON_UNESCAPED_UNICODE).json_encode($params,JSON_UNESCAPED_UNICODE)."\r\n\r\n");
        return false;
    }
    curl_close($ch);
    log_message($url." -- ".$type." -- ".var_export($params,true));
    return $temp;
}

/**
 * @param $type
 * @param $url
 * @param array $params
 * @param array $header
 * @return mixed
 *  输出
 */
function curl_json($type, $url, $params = [], $header = ["Accept-Charset: utf-8"]){
    $text = curl_text($type, $url, $params, $header);
    if( $text === false ) return false;
    return json_decode($text,true);
}

/**
 * @param $result
 * @param string $message
 * 成功提示
 */
function rsp_success_json($result=[],$message = 'success'){
    $data = [
        'trace_id'=>'',
        'code'=>0,
        'content'=>$result,
        'message'=>$message,
    ];
    $span_info = Yaf_Registry::get('span_info');
    if( $span_info && isset($span_info['x-b3-traceid']) ){
        $data['trace_id'] = $span_info['x-b3-traceid'];
    }
    $result = json_encode($data, JSON_UNESCAPED_UNICODE);
    rsp_setting($result);
}

if (!function_exists('getCrossMonthNum')) {
    function getCrossMonthNum($date1,$date2){
        $date1_stamp=strtotime($date1);
        $date2_stamp=strtotime($date2);
        list($date_1['y'],$date_1['m'])=explode("-",date('Y-m',$date1_stamp));
        list($date_2['y'],$date_2['m'])=explode("-",date('Y-m',$date2_stamp));
        return ($date_2['y']-$date_1['y'])*12 +$date_2['m']-$date_1['m'];
    }
}

/**
 * @param $code
 * @param string $message
 * 错误提示
 */
function rsp_die_json($code,$message = 'fail',$content='',$debug=''){
    // code 定义规范 根据code.php所定义的code
    $codes = require_once(CONFIG_PATH ."/code.php");
    if( !isset($codes[$code]) ){
        $code = 90001;
        $message = $code.' 系统定义错误,请联系开发人员 ';
    }
    $data = [
        'trace_id'=>'',
        'code'=>$code,
        'message'=>$message,
        'debug'=>$debug,
        'content'=>$content,
    ];
    $span_info = Yaf_Registry::get('span_info');
    if( $span_info && isset($span_info['x-b3-traceid']) ){
        $data['trace_id'] = $span_info['x-b3-traceid'];
    }
    if(Tool_YafTracer::checkSpan()){
        Yaf_Registry::get('span')->log(['code'=>$code, 'message'=>$message]);
    }
    $result = json_encode($data, JSON_UNESCAPED_UNICODE);
    rsp_setting($result);
}

/**
 * @param $result
 * 输出提示
 */
function rsp_setting($result){
    $response = new Yaf_Response_Http();
    $response->setHeader('Content-Type', 'application/json;charset=utf-8');
    $response->setBody($result);
    $response->response();
    //完成jaeger记录
    Tool_YafTracer::finish();
    //推送
    Tool_YafTracer::flush();
    die();
}


/**
 * @return bool
 * 检测数组array中的key是否存在且为真
 * isTrueKey($array,$key1,$key2,$key3,...)
 */
function isTrueKey(){
    $num = func_num_args();
    if($num <= 1) return false;

    $arg = func_get_args();

    $result = true; $array = $arg[0];
    for($i = 1; $i < $num; $i++) {
        $key = $arg[$i];
        if( !isset($array[$key]) || (is_string($array[$key]) && !$array[$key]) || (is_array($array[$key]) && empty($array[$key])) ){
            $result = false; break;
        }
    }
    return $result;
}


/**
 * @function    checkParams                             检查传入的参数是否缺失
 * @param       array               $params             需要检查的参数数组
 * @param       array               $required_params    必传参数数组
 * @return      array|bool                              返回布尔值或缺失的参数数组
 */
function checkParams($params,$required_params){
    if( !is_array($params) || !is_array($required_params) ) return false;

    $params_keys = array_keys($params);

    if( array_intersect($required_params,$params_keys) != $required_params ){
        $missing = array_diff($required_params,$params_keys);
        if( !empty($missing) ) return $missing;
    }
    return true;
}

function unsetEmptyParams(&$params){
    foreach($params as $key=>$value){
        if(empty($value) && $value===''){
            unset($params[$key]);
        }
    }
}

function checkEmptyParams($params,$required_params){
    if( !is_array($params) || !is_array($required_params) ){
        return false;
    }
    $missing = [];
    $params_keys = array_keys($params);
    if( array_intersect($required_params,$params_keys) != $required_params ){
        $missing = array_diff($required_params,$params_keys);
    }
    if( !empty($missing) ) return $missing;
    foreach($required_params as $value){
        if(!isset($params[$value])){
            $missing [] = $value;
        }
    }
    if( !empty($missing) ) return $missing;
    return true;
}


function getConfig($file){
    $env = Yaf_Application::app()->getConfig()->config->env;
    return new Yaf_Config_Ini(CONFIG_PATH . "/ini/" . $env .'/'.$file);
}
if (!function_exists('parse_controller_action')) {
    function parse_controller_action ($action = '') {
        if (!preg_match('/^[a-zA-Z\_]+(\/[a-z1-9A-Z\_]+)+$/', $action)) rsp_die_json(10001, 'action格式错误');
        return array_map(function ($m) {
            return strtolower($m);
        },explode('/',$action));
    }
}


function many_array_column($datas,$key){
    $tmp = [];
    if(!empty($datas)){
        foreach($datas as $m){
            $tmp[$m[$key]] = $m;
        }
    }
    return $tmp;
}

function getArraysOfvalue ($box,$id,$key)
{
    return (!empty($box) && isset($box[$id]) && isset($box[$id][$key]) ) ? $box[$id][$key] : '';
}
if(!function_exists('is_not_json')){
    function is_not_json($str){
        if( !is_string($str) ) return true;
        return is_null(json_decode($str));
    }
}

function resource_id_generator($type_id = 0)
{
    if (!$type_id) return false;
    $resource = new Comm_Curl([ 'service'=>'resource','format'=>'json']);
    $id = $resource->post('/resource/id/generator', ['type_id' => $type_id]);
    if ($id['code'] !== 0 || !isTrueKey($id, 'content')) return false;
    return (string)$id['content'];
}


function initParams($params,$field,$data=[]){
    if(empty($params) || empty($field)) return [];
    foreach ($field as $item){
        if(isset($params[$item])) $data[$item] = is_array($params[$item]) ? $params[$item] : trim($params[$item]);
    }
    return $data;
}

function getTag($data,$field,$ids=[]){
    if(!$data || !$field) return [];
    foreach($field as $t){
        $ids = array_merge($ids,array_column($data, $t));
    }
    $ids =  array_filter(array_unique($ids));
    $tag_host = new Comm_Curl([ 'service'=>'tag','format'=>'json']);
    $tmp = $tag_host->post('/tag/lists',['tag_ids'=>$ids]);
    $tags = $tmp ? many_array_column($tmp['content'], 'tag_id') : [];
    return $tags;
}


/**
 * @param $mobile
 * @return bool
 * 验证手机号
 */
function isMobile($mobile){
    if(!$mobile) return false;
    return preg_match('/^20[\d]{9}$|^13[\d]{9}$|^14[5,7,9]{1}\d{8}$|^15[^4]{1}\d{8}$|^16[\d]{9}$|^17[\d]{9}$|^18[\d]{9}$|^19[\d]{9}$/', (String)$mobile) ? true : false;
}

function get_empty_fields($fields = [], $params = []){
    return array_diff($fields, array_keys(array_filter($params, function ($m) {
        return is_array($m) ? !!$m : trim($m) !== '';
    })));
}


function is_not_empty($data,$key){
    if( !is_array($data) ) return false;
    if( isset($data[$key]) && $data[$key] != '') return true;
    return false;
}

function get_error_code($code,$message,$language='CN'){
    if(!$code) return 'missing code';
    $tips_host = new Comm_Curl([ 'service'=>'tips','format'=>'json']);
    $tmp = $tips_host->post('/message/show',['code'=>$code,'language'=>$language]);
    if($tmp['code']==0 && !empty($tmp['content'])){
        switch ($tmp['content']['splice_type'])
        {
            case 'prefix':
                $message = $message.' '.$tmp['content']['message'];
                break;
            case 'suffix':
                $message = $tmp['content']['message'].' '.$message;
                break;
            default:
                $message = $tmp['content']['message'];
        }
        return ['code'=>$tmp['content']['prefix'].$tmp['content']['code'],'message'=>$message,'content'=>''];
    }
    return ['code'=>$code,'message'=>$message,'content'=>''];
}

/**
 * @param $code
 * @param string $message
 * 错误提示
 */
function rsp_error_tips($code,$message = '',$language='CN'){
    $tips = get_error_code($code,$message,$language);
    $span_info = Yaf_Registry::get('span_info');
    $tips['trace_id'] = '';
    if( $span_info && isset($span_info['x-b3-traceid']) ){
        $tips['trace_id'] = $span_info['x-b3-traceid'];
    }
    if(Tool_YafTracer::checkSpan()){
        Yaf_Registry::get('span')->log(['code'=>$code, 'message'=>$message]);
    }
    $result = json_encode($tips, JSON_UNESCAPED_UNICODE);
    rsp_setting($result);
}

/**
 * 去除数组中字符串首尾的空格
 * @param  array  $arr
 * @return array
 */
function array_trim( $arr){
    if( !is_array($arr) ){
        return  $arr;
    }
    $res = [];
    foreach ($arr as $key=>$value){
        if( is_string($value) ){
            $value = trim($value);
        }
        $res[$key] = $value;
    }
    return $res;
}

if(!function_exists('NumToCNMoney')){
    /**
     * 数字金额转大写
     * @param $num
     * @return string
     */
    function NumToCNMoney($num){
        $c1 = "零壹贰叁肆伍陆柒捌玖";
        $c2 = "分角元拾佰仟万拾佰仟亿";
        //精确到分后面就不要了，所以只留两个小数位
        $num = round($num, 2);
        //将数字转化为整数
        $num = $num * 100;
        if (strlen($num) > 10) {
            return "金额太大，请检查";
        }
        $i = 0;
        $c = "";
        while (1) {
            if ($i == 0) {
                //获取最后一位数字
                $n = substr($num, strlen($num)-1, 1);
            } else {
                $n = $num % 10;
            }
            //每次将最后一位数字转化为中文
            $p1 = substr($c1, 3 * $n, 3);
            $p2 = substr($c2, 3 * $i, 3);
            if ($n != '0' || ($n == '0' && ($p2 == '亿' || $p2 == '万' || $p2 == '元'))) {
                $c = $p1 . $p2 . $c;
            } else {
                $c = $p1 . $c;
            }
            $i = $i + 1;
            //去掉数字最后一位了
            $num = $num / 10;
            $num = (int)$num;
            //结束循环
            if ($num == 0) {
                break;
            }
        }
        $j = 0;
        $slen = strlen($c);
        while ($j < $slen) {
            //utf8一个汉字相当3个字符
            $m = substr($c, $j, 6);
            //处理数字中很多0的情况,每次循环去掉一个汉字“零”
            if ($m == '零元' || $m == '零万' || $m == '零亿' || $m == '零零') {
                $left = substr($c, 0, $j);
                $right = substr($c, $j + 3);
                $c = $left . $right;
                $j = $j-3;
                $slen = $slen-3;
            }
            $j = $j + 3;
        }
        //这个是为了去掉类似23.0中最后一个“零”字
        if (substr($c, strlen($c)-3, 3) == '零') {
            $c = substr($c, 0, strlen($c)-3);
        }
        //将处理的汉字加上“整”
        if (empty($c)) {
            return  "零元整";
        }else{
            return  $c . "整";
        }
    }
}

if(!function_exists('retain_two_decimal')){
    /**
     * 保留两位小数
     * @param $data
     * @param $key
     */
    function retain_two_decimal($data,$key){
        return isset($data[$key]) ? sprintf("%.2f",$data[$key]) : '0.00';
    }
}

if(!function_exists('order_sn')){
    function order_sn(){
        $sn_url = getConfig('ms.ini')->get('sn.url');
        $get_sn = curl_json("get", $sn_url,[]);
        return preg_match("/\d{10,}$/",$get_sn) ? $get_sn : false;
    }
}

if (!function_exists('set_log')) {
    /**
     * 设置jaeger log
     * @param  array  $array
     * @param  null  $timestamp
     */
    function set_log(array $array, $timestamp = null)
    {
        if (Tool_YafTracer::checkSpan()) {
            Yaf_Registry::get('span')->log($array, $timestamp);
        }
    }
}

if (!function_exists('set_tag')) {
    /**
     * 设置jaeger tag
     * @param $key
     * @param $value
     * @throws Exception
     */
    function set_tag($key, $value)
    {
        if (Tool_YafTracer::checkSpan()) {
            Yaf_Registry::get('span')->setTag($key, $value);
        }
    }
}

if (!function_exists('set_tags')) {
    /**
     * 设置jaeger tags
     * @param  array  $params
     * @throws Exception
     */
    function set_tags(array $params)
    {
        if (Tool_YafTracer::checkSpan()) {
            Yaf_Registry::get('span')->setTags($params);
        }
    }
}

if (!function_exists('rSnowFlake')) {
    /**
     * 根据订单号获取支付时间
     * @param $tnum
     * @return float|int
     */
    function rSnowFlake($tnum)
    {
        $tnum = substr($tnum,0,-5);
        $config = getConfig('other.ini');
        $epoch = $config->snowflake->epoch ?? 1577808000000;
        $before = $epoch + ($tnum>>22);
        $time = $before/1000;
        return $time;
    }
}

if (!function_exists('dateFormat')) {
    /**
     * 根据时间戳获取时间格式，传0则返回为空
     * @param $time    时间戳
     * @return float|int
     */
    function dateFormat($time)
    {
        return !empty($time)?date('Y-m-d H:i:s',$time):'';
    }
}

if (!function_exists('send_email')) {
    /**
     * @param array $receivers 收件人列表
     * @param string $subject 标题
     * @param string $body 正文
     * @param array $attachments 附件列表
     * @param array $cc 抄送人列表
     * @return bool
     */
    function send_email($receivers = [], $subject = '', $body = '', $attachments = [], $cc = [])
    {
        if (!$receivers || !$subject || !$body) return false;
        if (!is_array($receivers)) $receivers = [$receivers];
        $config = getConfig('other.ini');
        $username = $config->get('email.username') ?: '';
        $password = $config->get('email.password') ?: '';
        if (!$username || !$password) {
            log_message('----send_email----' . json_encode(['error' => '邮箱未配置']));
            return false;
        }

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
//            $mail->SMTPDebug = 2;
            $mail->isSMTP();
            $mail->Host = 'smtp.exmail.qq.com';
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->SMTPSecure = 'ssl';
            $mail->Port = 465;
            $mail->CharSet = "utf-8"; //设置字符集编码

            // 收件人
            $mail->setFrom($username, '社区云');
            foreach ($receivers as $receiver) $mail->addAddress($receiver);

            // 抄送
            if ($cc) {
                if (!is_array($cc)) $cc = [$cc];
                foreach ($cc as $item) $mail->addCC($item);
            }

            // 附件
            if ($attachments) {
                if (!is_array($attachments)) $attachments = [$attachments];
                foreach ($attachments as $attachment) $mail->addAttachment($attachment);
            }

            //Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();
        } catch (\Exception $e) {
            log_message('----send_email----' . json_encode(['error' => $e->getMessage()]));
            return false;
        }
        return true;
    }
}

if (!function_exists('info')) {
    function info($msg, $data)
    {
        $filename = 'error';
        if (!$data) return false;
        $data = is_array($data) ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $data;
        $msg .= $data;

        $path = SERVICE_PATH."/data/logs/";
        if( is_dir($path) == false ) mkdir($path);
        $date = date('Y-m-d');

        $file = ERROR_LOG_FILE;
        if( $file != $path.$filename."-".$date.".log" ) $file = $path.$filename."-".$date.".log";

        if( @ini_get('error_log') != $file ) @ini_set('error_log',$file);
        $span_info = Yaf_Registry::get('span_info');
        if( $span_info && isset($span_info['x-b3-traceid']) ){
            $msg = 'traceid:'.$span_info['x-b3-traceid'].','.$msg;
        }
        error_log(date('Y-m-d H:i:s')."  ".$msg."\n", 3, $file);
    }
}

if (!function_exists('isPlate')) {
    function isPlate($license)
    {
        if (empty($license)) {
            return false;
        }
        #匹配民用车牌和使馆车牌
        # 判断标准
        # 1，第一位为汉字省份缩写
        # 2，第二位为大写字母城市编码
        # 3，后面是5位仅含字母和数字的组合
        {
            $regular = "/^[京津冀晋蒙辽吉黑沪苏浙皖闽赣鲁豫鄂湘粤粵桂琼川贵云渝藏陕甘青宁新使]{1}[A-Z]{1}[0-9a-zA-Z]{5,6}$/u";
            preg_match($regular, $license, $match);
            if (isset($match[0])) {
                return true;
            }
        }

        #匹配特种车牌(挂,警,学,领,港,澳)
        #参考 https://wenku.baidu.com/view/4573909a964bcf84b9d57bc5.html
        {
            $regular = '/[京津冀晋蒙辽吉黑沪苏浙皖闽赣鲁豫鄂湘粤粵桂琼川贵云渝藏陕甘青宁新]{1}[1-9A-Z]{1}[0-9a-zA-Z]{4,5}[挂警学领港澳]{1}$/u';
            preg_match($regular, $license, $match);
            if (isset($match[0])) {
                return true;
            }
        }

        #匹配使馆车辆
        {
            $regular = "/[A-Z0-9]{5,6}使$/";
            preg_match($regular, $license, $match);
            if (isset($match[0])) {
                return true;
            }
        }

        #匹配武警车牌
        #参考 https://wenku.baidu.com/view/7fe0b333aaea998fcc220e48.html
        {
            $regular = '/^WJ[0-9]{5}/i';
            preg_match($regular, $license, $match);
            if (isset($match[0])) return true;

            $regular = '/^WJ[0-9]{4}[XBTSHJD]$/i';
            preg_match($regular, $license, $match);
            if (isset($match[0])) return true;

            $regular = '/^WJ[京津冀晋蒙辽吉黑沪苏浙皖闽赣鲁豫鄂湘粤桂琼川贵云渝藏陕甘青宁新]{1}[0-9]{5}$/u';
            preg_match($regular, $license, $match);
            if (isset($match[0])) return true;

            $regular = '/^WJ[京津冀晋蒙辽吉黑沪苏浙皖闽赣鲁豫鄂湘粤桂琼川贵云渝藏陕甘青宁新]{1}[0-9]{4}[XBTSHJD]$/u';
            preg_match($regular, $license, $match);
            if (isset($match[0])) return true;

            $regular = '/^(KM|KJ)[0-9]{4,5}$/i';
            preg_match($regular, $license, $match);
            if (isset($match[0])) return true;
        }
        return false;
    }
}

if (!function_exists('validate_date')) {
    /**
     * @日期格式检测函数
     * @param  string  $date  输入的字符串日期
     * @param  string  $format  期望的格式
     * @return  bool
     */
    function validate_date($date, $format = 'Y-m-d H:i:s')
    {
        $t = strtotime($date);
        return $t && date($format, $t) == $date;
    }
}

if (!function_exists('check_date')) {
    /**
     * 便捷检查日期函数，Y-m-d或Y-n-j格式
     * @param $date
     * @return bool
     */
    function check_date($date)
    {
        $date = trim($date);
        if (!validate_date($date, 'Y-m-d') && !validate_date($date, 'Y-n-j')) {
            return false;
        }
        return true;
    }
}

if (!function_exists('twoArraySetColumns')) {
    function twoArraySetColumns($data,$columns)
    {
        $return = [];
        foreach ($data as $k => $v){
            $arr = [];
            foreach ($columns as $c => $val){
                $arr[$c] = $v[$val] ?? '';
            }
            $return[] = $arr;
        }
        return $return;
    }
}

/**
 * @param string $prefix
 * @param string $connetion
 * @param int $append
 * @return string
 * 生成唯一uuid
 */
if (!function_exists('uuid')) {
    function uuid($prefix = '', $connetion = '-', $append = 0)
    {
        $chars = md5(uniqid(mt_rand(), true));
        $uuid = substr($chars, 0, 8) . $connetion;
        $uuid .= substr($chars, 8, 4) . $connetion;
        $uuid .= substr($chars, 12, 4) . $connetion;
        $uuid .= substr($chars, 16, 4) . $connetion;
        $uuid .= substr($chars, 20, 12);
        if ($append) {
            $chars = md5(uniqid(mt_rand(), true)) . rand(1, 99999999);
            for ($i = 1; $i <= $append; $i++) {
                $uuid .= $connetion . substr($chars, rand(0, 20), 8);
            }
        }
        if ($prefix) {
            $uuid .= $connetion . $prefix;
        }
        return $uuid;
    }
}

if (!function_exists('check_mobile')) {
    function check_mobile($mobile){
        return (bool)preg_match('/^\d{1,11}$/',$mobile);
    }
}

if (!function_exists('array_ufc')) {
    function array_ufc(array $arr, $key)
    {
        if (empty($arr)) {
            return [];
        }
        return array_unique(array_filter(array_column($arr, $key)));
    }
}

if (!function_exists('str_filter')) {
    function str_filter(string $str, $search = [' ', "\r", "\n"])
    {
        return str_replace($search, '', $str);
    }
}

if (!function_exists('replaceSpecialChar')) {
    function replaceSpecialChar($strParam){
        $regex = "/\/|\!|\#|\\$|\%|\^|\&|\*|\(|\)|\{|\}|\<|\>|\[|\]|\,|\/|\'|\`|\=|\\\|\|/";
        return preg_replace($regex,"",$strParam);
    }
}

if(!function_exists('textGbkToUtf8')) {
    function textGbkToUtf8($text)
    {
        $text = trim($text);
        $code = mb_detect_encoding($text, ['ASCII', 'UTF-8', 'GBK', 'GB2312', 'CP936', 'BIG5']);
        if (strtoupper($code) == "GBK") {
            // return iconv('UTF-8','GBK',$text);
            return mb_convert_encoding($text, 'UTF-8', strtoupper($code));
        }
        return (string)$text;
    }
}

if(!function_exists('encode_plate')){
    function encode_plate($plate) {
        $plate = strtoupper($plate);
        if (mb_strlen($plate) > 10) {
            info("---new-plate-encode-error-", [$plate]);
            return 0;
        }
        if (mb_strlen($plate) < 4) {
            info("---new-plate-encode-error-", [$plate]);
            return 0;
        }

        $map = [
            ''=>0,
            '0'=>10,  '1'=>11,    '2'=>12,  '3'=>13,    '4'=>14,
            '5'=>15,  '6'=>16,    '7'=>17,  '8'=>18,    '9'=>19,
            'A'=>20,  'B'=>21,    'C'=>22,  'D'=>23,    'E'=>24,
            'F'=>25,  'G'=>26,    'H'=>27,  'I'=>28,    'J'=>29,
            'K'=>30,  'L'=>31,    'M'=>32,  'N'=>33,    'O'=>34,
            'P'=>35,  'Q'=>36,    'R'=>37,  'S'=>38,    'T'=>39,
            'U'=>40,  'V'=>41,    'W'=>42,  'X'=>43,    'Y'=>44,
            'Z'=>45,
            "京"=>50,"沪"=>51,"浙"=>52,"苏"=>53,"粤"=>54,
            "鲁"=>55,"晋"=>56,"冀"=>57,"豫"=>58,"川"=>59,
            "渝"=>60,"辽"=>61,"吉"=>62,"黑"=>63,"皖"=>64,
            "鄂"=>65,"津"=>66,"贵"=>67,"云"=>68,"桂"=>69,
            "琼"=>70,"青"=>71,"新"=>72,"藏"=>73,"蒙"=>74,
            "宁"=>75,"甘"=>76,"陕"=>77,"闽"=>78,"赣"=>79,
            "湘"=>80,"使"=>81,"挂"=>82,"学"=>83,"警"=>84,
            "军"=>85,"港"=>86,"澳"=>87,"领"=>88,"彩"=>89,
            "粵"=>90
        ];
        $elements=(preg_split('//u', $plate, null, PREG_SPLIT_NO_EMPTY));
        $index = 0;
        $length = count($elements);
        for ($i =0 ;$i<2;$i++){
            if(!isset($map[$elements[$i]])){
                info("---new-plate-encode-error-", [$plate]);
                return 0;
            }
            $temp=(int)$map[$elements[$i]]<< (8*(7-$i));
            $index+=$temp;
        }
        $end = 0;
        for ($j =2 ;$j<$length;$j++){
            if(!isset($map[$elements[$j]])){
                info("---new-plate-encode-error-", [$plate]);
                return 0;
            }
            $temp=(int)$map[$elements[$j]]<< (6*(10-$j-1));
            $end+=$temp;
        }
        return $index+$end;
    }
}

if(!function_exists('decode_plate')){
    function decode_plate($car_id) {
        $map = [
            ''=>0,
            '0'=>10,  '1'=>11,    '2'=>12,  '3'=>13,    '4'=>14,
            '5'=>15,  '6'=>16,    '7'=>17,  '8'=>18,    '9'=>19,
            'A'=>20,  'B'=>21,    'C'=>22,  'D'=>23,    'E'=>24,
            'F'=>25,  'G'=>26,    'H'=>27,  'I'=>28,    'J'=>29,
            'K'=>30,  'L'=>31,    'M'=>32,  'N'=>33,    'O'=>34,
            'P'=>35,  'Q'=>36,    'R'=>37,  'S'=>38,    'T'=>39,
            'U'=>40,  'V'=>41,    'W'=>42,  'X'=>43,    'Y'=>44,
            'Z'=>45,
            "京"=>50,"沪"=>51,"浙"=>52,"苏"=>53,"粤"=>54,
            "鲁"=>55,"晋"=>56,"冀"=>57,"豫"=>58,"川"=>59,
            "渝"=>60,"辽"=>61,"吉"=>62,"黑"=>63,"皖"=>64,
            "鄂"=>65,"津"=>66,"贵"=>67,"云"=>68,"桂"=>69,
            "琼"=>70,"青"=>71,"新"=>72,"藏"=>73,"蒙"=>74,
            "宁"=>75,"甘"=>76,"陕"=>77,"闽"=>78,"赣"=>79,
            "湘"=>80,"使"=>81,"挂"=>82,"学"=>83,"警"=>84,
            "军"=>85,"港"=>86,"澳"=>87,"领"=>88,"彩"=>89,
            "粵"=>90
        ];
        $rmap = array();
        foreach ($map as $key => $value) {
            $rmap[$value]=$key;
        }
        $plate='';
        for ($j=0; $j <=7; $j++) {
            $temp=($car_id >> (6*$j)) & 0x3f;
            if (!isset($rmap[$temp])) {
                log_message('----car_id_error----'.$car_id);
                return '';
            }
            $plate=$rmap[$temp].$plate;
        }
        for ($i=6; $i <=7; $i++) {
            $temp=($car_id >> (8*$i)) & 0xFF;
            if (!isset($rmap[$temp])) {
                log_message('----car_id_error----'.$car_id);
                return '';
            }
            $plate=$rmap[$temp].$plate;
        }
        return $plate;
    }
}

/**
 * @param int $code
 * @param string $msg
 * @param string $data
 * @return array
 * 返回提示信息
 */
if (!function_exists('returnCode')) {
    function returnCode($code = 0, $msg = 'success', $data = '')
    {
        return ['code' => $code, 'message' => $msg, 'content' => $data];
    }
}

