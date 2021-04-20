<?php
include_once APP_PATH . "/BaseController.php";

use Session\ConstantModel as Constant;

class GatewayController extends BaseController
{
    public function indexAction()
    {
        $post = $this->getRequest()->getPost();
        $post = array_trim($post);
        $required = ['app_id', 'method', 'format', 'charset', 'timestamp', 'token', 'biz_content'];
        //参数检测
        $check_params_info = checkParams($post, $required);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . ' 参数缺失');
        }

        $this->token($post['token'], $post['app_id']) or rsp_die_json(90000, '登录时间过期或者检验失败，请重新登录');
        $params = $this->parseBizContent($post['biz_content']);

        $map = $this->method_map($post['method'], $post['app_id']);
        if ($map === false) {
            rsp_die_json(10002, 'method 不存在');
        }
        $this->forward($map['module'], $map['controller'], $map['action'], $params);
    }

    private function token($token, $app_id)
    {
        $cfg = getConfig('ms.ini');
        $oauthUrl = $cfg->auth2->url ?? '';
        $info = curl_json("get", $oauthUrl . "/userinfo?access_token=" . $token);
        log_message('token:' . json_encode($info));
        // todo 用户被挤下线提示
        if ($info && $info['code'] == '90003') {
            rsp_die_json($info['code'], $info['message']);
        }
        if (!$info || $info['code'] != 0) {
            return false;
        }
        $memberInfo = $info['content']['member_info'] ?? [];
        $appInfo = $info['content']['app_info'] ?? [];

        $member_app_id = $memberInfo['oauth_app_id'] ?? '';
        $oauth_app_id = $member_app_id ?: ($appInfo['oauth_app_id'] ?? '');
        $_SESSION['oauth_app_id'] = $oauth_app_id;

        $oauth_subapp_id = $memberInfo['oauth_subapp_id'] ?? 0;
        $_SESSION['oauth_subapp_id'] = $oauth_subapp_id ?: ($appInfo['oauth_subapp_id'] ?? 0);

        if (empty($oauth_app_id) || $app_id !== $oauth_app_id) {
            return false;
        }
        // todo 保存member和app信息
        $this->setSessionColoum($memberInfo, Constant::MEMBER_COLUMNS);
        $this->setSessionColoum($appInfo, Constant::APP_COLUMNS);

        // todo 管理员项目权限查询
        if (isset($_SESSION['employee_id'])) {
            $permission = [
                'access_token' => $token,
                'employee_id' => $_SESSION['employee_id']
            ];
            curl_json("post", $oauthUrl . "/user/project/permission", $permission);
        }
        return true;
    }

    private function method_map($method, $appid = '')
    {
        // 判断第三方内部请求method限制
        $cfg = getConfig('other.ini');
        $appids = $cfg->get('limit.gateway.appids');
        $appids = $appids ? explode(",", $appids) : [];
        $file = CONFIG_PATH . "/method/thirdParty/{$appid}.php";
        $scope = ($_SESSION['member_scope'] ?? '') ?: ($_SESSION['app_scope'] ?? '');
        if (in_array($appid, $appids) && file_exists($file) && $scope == 'third_party') {
            $map = require_once($file);
        } else {
            $map = require_once(CONFIG_PATH . "/rootMap.php");
        }
        return isset($map[$method]) ? $map[$method] : false;
    }

    private function parseBizContent($biz_content)
    {
        $params = [];
        $tmp = json_decode($biz_content, true);
        if ($tmp && is_array($tmp)) $params = $tmp;
        return $params;
    }

    private function setSessionColoum($data, $columns)
    {
        $fillData = array_flip($columns);
        array_map(function ($m) use ($data, $fillData) {
            $_SESSION[$fillData[$m]] = $data[$m] ?? '';
        }, $columns);
    }
}

?>
