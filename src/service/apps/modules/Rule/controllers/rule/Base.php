<?php

include_once RULE_MODULE_PATH . "/controllers/Common.php";

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class Base
{
    use Common;

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
        'rule' => 10028,
    ];

    protected $employee_id;
    protected $from_id; // js from_id
    protected $app_id;
    protected $sub_app_id;
    protected $project_id;

    public function __construct()
    {
        $this->employee_id = $_SESSION['employee_id'] ?: '';
        $this->app_id = $_SESSION['oauth_app_id'] ?: '';
        $this->sub_app_id = $_SESSION['oauth_subapp_id'] ?: '';
        $this->project_id = $_SESSION['member_project_id'];
    }


    /**
     * @param $uri
     * @param $params
     * @return array
     */
    public function post($uri, $params)
    {
        log_message('POST RULE-ENGINE:' . json_encode([$uri, $params]));
        try {
            $body = $this->initCurl($params)
                ->post($uri, ['json' => $params])
                ->getBody()
                ->getContents();

            $result = json_decode($body, true);
            if ($result['code'] !== 0) {
                // $this->error($result);
                log_message('POST ERROR:' . $result['message']);
                $this->error(['code' => $result['code'], 'message' => $result['message']]);
            }

            return $result['content'] ?: [];

        } catch (GuzzleException $exception) {
            log_message('curl err :' . $exception->getMessage());
            $this->error(['code' => 500, 'message' => '系统繁忙请稍后再试']);
        }
    }

    /**
     * @param $uri
     * @param $params
     * @return array
     */
    public function get($uri, $params)
    {
        log_message('GET RULE-ENGINE:' . json_encode([$uri, $params]));
        try {
            $body = $this->initCurl($params)
                ->get($uri, ['query' => $params])
                ->getBody()
                ->getContents();
            $result = json_decode($body, true);
            if ($result['code'] !== 0) {
                // $this->error($result);
                log_message('GET ERROR:' . $result['message']);
                $this->error(['code' => $result['code'], 'message' => $result['message']]);
            }

            return $result['content'] ?: [];

        } catch (GuzzleException $exception) {
            log_message('curl err :' . $exception->getMessage());
            $this->error(['code' => 500, 'message' => '系统繁忙请稍后再试']);
        }
    }


    /**
     * @param array $params
     * @param int $timeout
     * @return \GuzzleHttp\Client
     */
    private function initCurl(&$params = [], $timeout = 10)
    {
        $permission_key = !empty($_GET['permissions_key']) ? $_GET['permissions_key'] : '';
        if (!empty($params['project_ids']) && 'all' == $params["project_ids"]) {
            $params = (new AuthModel())->getPermissionProjects($permission_key, $params);
        }
        return new GuzzleHttp\Client([
            'base_uri' => getConfig('ms.ini')->get('rule.url'),
            'headers' => [
                'Oauth-App-Id' => $this->app_id,
                'Oauth-SubApp-Id' => $this->sub_app_id,
                'Operator-Id' => $this->employee_id, // 当前操作人（登录用户）
                'Project-Id' => $this->project_id,
            ],
            'timeout' => $timeout,
        ]);
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
        $code = $response['code'] ?? '';
        $message = $response['message'] ?? '';
        rsp_error_tips($code, $message);
    }
}