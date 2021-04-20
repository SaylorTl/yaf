<?php

class Base
{

    use CommonController;
    protected $adv;
    protected $pm;
    protected $user;

    protected $employee_id;

    protected $project_id;

    /**
     * parameters filter
     */
    const T_RAW = 0x1;
    const T_INT = 0x2;
    const T_URL = 0x8;
    const T_JSON = 0x10;
    const T_BOOL = 0x20;
    const T_FLOAT = 0x40;
    const T_EMAIL = 0x80;
    const T_STRING = 0x100;
    const T_DATE = 0x200;


    const RESOURCE_TYPES = [
        'adv' => 10011,
    ];

    public function __construct()
    {
        $this->adv = new Comm_Curl(['service' => 'adv', 'format' => 'json']);
        $this->pm = new Comm_Curl(['service' => 'pm', 'format' => 'json']);
        $this->user = new Comm_Curl([ 'service'=> 'user','format'=>'json']);

        $this->employee_id = $_SESSION['employee_id'] ?? '';
        $this->project_id = $_SESSION['member_project_id'] ?? '';
    }


}


trait CommonController
{


    /**
     * @param $var
     * @param $type
     * @return array|bool|float|int|mixed
     */
    public static function filter($var, $type)
    {
        if ($type & self::T_RAW) {
            return $var;
        }

        // date
        if ($type & self::T_DATE) {
            if (strtotime($var)) {
                return $var;
            }

            return false;
        }

        // int
        if ($type & self::T_INT) {
            if (!is_numeric($var)) {
                return false;
            }

            return intval($var);
        }

        // float
        if ($type & self::T_FLOAT) {
            if (!is_numeric($var)) {
                return false;
            }

            return doubleval($var);
        }

        // boolean
        if ($type & self::T_BOOL) {
            return empty($var) ? 0 : 1;
        }

        // email
        if ($type & self::T_EMAIL) {
            return filter_var($var, FILTER_VALIDATE_EMAIL);
        }

        // url
        if ($type & self::T_URL) {
            if (!filter_var($var, FILTER_VALIDATE_URL)) {
                return filter_var(urldecode($var), FILTER_VALIDATE_URL);
            }

            return filter_var($var, FILTER_VALIDATE_URL);
        }

        // strip html tags
        if ($type & self::T_JSON) {
            if (is_array($var)) {
                return $var;
            }

            $jd = json_decode($var, true);
            if (!is_array($jd)) {
                return false;
            }

            return $jd;
        }

        return trim($var);
    }

    public function post($server, $uri, $params)
    {
        $result = $this->$server->post($uri, $params);

        if ($result['code'] !== 0) {
            $this->error($result);
        }

        return $result['content'] ?: [];
    }

    /**
     * @param $result
     * @param string $message
     */
    public function success($result, $message = 'success')
    {
        $result = json_encode(['code' => 0, 'message' => $message, 'content' => $result], JSON_UNESCAPED_UNICODE);
        rsp_setting($result);
    }


    /**
     * @param $response
     */
    public function error($response)
    {
        $code = $response['code'];
        $message = $response['message'];
        $debug = $response['debug'] ?? '';

        // code 定义规范 根据code.php所定义的code
        $codes = require_once(CONFIG_PATH . "/code.php");
        if (!isset($codes[$code])) {
            $code = 90001;
            $message = $code . ' 系统定义错误,请联系开发人员 ';
        }

        $response = ['code' => $code, 'message' => $message, 'content' => ''];
        if ($debug) {
            $response['debug'] = $debug;
        }
        $result = json_encode($response, JSON_UNESCAPED_UNICODE);
        rsp_setting($result);
    }


    /**
     * @param $definition
     * @param  $request
     * @param bool $required
     * @return array
     * 参数过滤
     */
    protected function parameters($definition, $request, $required = false)
    {
        $parameters = [];
        foreach ($definition as $key => $filter) {
            $result = $request[$key] ?? null;
            if ($result !== null && $result !== '') {

                $result = self::filter($result, $filter);
                if ($result === false) {
                    $this->error(['code' => '10001', 'message' => "Parameter '{$key}' is invalid"]);
                }
            } else {
                if ($required) {
                    $this->error(['code' => '10002', 'message' => "Parameter '{$key}' is required"]);
                }
                continue;
            }

            $parameters[$key] = $result;
        }

        return $parameters;
    }


}
