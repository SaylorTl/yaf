<?php


class ClientController extends Yaf_Controller_Abstract
{
    protected $user;

    protected $pm;

    protected $resource;

    protected $wxtoken;

    protected $params = [];

    public function init()
    {
        Yaf_Dispatcher::getInstance()->disableView();
        $this->user = new Comm_Curl(['service' => 'user', 'format' => 'json']);
        $this->pm = new Comm_Curl(['service' => 'pm', 'format' => 'json']);
        $this->resource = new Comm_Curl(['service' => 'resource', 'format' => 'json']);

        $otherCfg = getConfig('other.ini');
        $third_app_id = $this->params['wx_third_app_id'] ?? $otherCfg->get('limit.wx.thirdauth.appid');
        $service = $third_app_id ? ['service' => 'a4wechat'] : ['service' => 'wxtoken', 'format' => 'json'];
        $this->wxtoken = new Comm_Curl($service);

        $this->params = $this->getRequest()->getPost();
        if (!isTrueKey($this->params, 'sign')) rsp_die_json(10001, '签名缺失');
        if (!$this->checkSign()) rsp_die_json(10001, '签名错误');
    }

    public function resourceLiteAction()
    {
        if (!isTrueKey($this->params, 'resource_lite')) rsp_error_tips(10001);
        $resource_id = $this->resource->post('/resource/id/lite', ['resource_lite' => $this->params['resource_lite']]);
        $resource_id = $resource_id['content'] ?? '';
        if (!$resource_id) rsp_success_json([]);

        $type_id = substr($resource_id, -5);
        $type = $this->resource->post('/type/show', ['type_id' => $type_id]);
        $type = $type['content'] ?? [];
        if (!$type) rsp_success_json([]);

        $device = $this->pm->post('/device/v2/lists', ['device_id' => $resource_id]);
        $device = $device['content'][0] ?? [];

        $project = $this->pm->post('/project/projects', ['project_id' => $resource_id]);
        $project = $project['content'][0] ?? [];

        if (!$device && !$project) rsp_success_json([]);
        rsp_success_json([
            'resource_id' => $resource_id,
            'resource_lite' => $this->params['resource_lite'],
            'type_id' => $type['type_id'] ?? '',
            'type_name' => $type['type_name'] ?? '',
            'type_cname' => $type['type_cname'] ?? '',
            'client_app_id' => $device['app_id'] ?? $project['app_id'] ?? '',
        ]);
    }

    public function employeesAction()
    {
        if (isTrueKey($this->params, ...['mobile'])) $this->getEmployeesByMobile();
        if (isTrueKey($this->params, ...['app_id', 'code'])) $this->getEmployeesByCode();
        rsp_error_tips(10001);
    }

    private function getEmployeesByMobile()
    {
        $employees = $this->user->post('/employee/lists', ['mobile' => $this->params['mobile'], 'page' => 1, 'pagesize' => 999]);
        $employees = $employees['content'] ?? [];
        if (!$employees) rsp_success_json([]);

        rsp_success_json(array_map(function ($m) {
            return [
                'employee_id' => $m['employee_id'],
                'mobile' => $m['mobile'],
                'client_app_id' => $m['app_id'],
            ];
        }, $employees));
    }

    private function getEmployeesByCode()
    {
        $openid = $this->wxtoken->get('/wxcode', Comm_Sign::a4wechat_make_sign([
                'app_id' => $this->params['app_id'],
                'code' => $this->params['code']]
        ));
        if ($openid['code'] !== 0) rsp_die_json(10001, $openid['message']);
        $session_key = $openid['content']['session_key'] ?? '';
        $openid = $openid['content']['openid'] ?? '';
        if (!$openid) rsp_success_json(['employees' => [], 'session_key' => $session_key]);
        $clients = $this->user->post('/client/lists', ['openid' => $openid, 'page' => 1, 'pagesize' => 999]);
        $clients = $clients['content'] ?? [];
        if (!$clients) rsp_success_json(['employees' => [], 'session_key' => $session_key]);
        $employee_ids = array_unique(array_filter(array_column($clients, 'employee_id')));
        if (!$employee_ids) rsp_success_json(['employees' => [], 'session_key' => $session_key]);
        $employees = $this->user->post('/employee/lists', ['employee_ids' => $employee_ids, 'page' => 1, 'pagesize' => 999]);
        $employees = $employees['content'] ?? [];
        if (!$employees) rsp_success_json(['employees' => [], 'session_key' => $session_key]);

        $employees = array_map(function ($m) {
            return [
                'employee_id' => $m['employee_id'],
                'mobile' => $m['mobile'],
                'client_app_id' => $m['app_id'],
            ];
        }, $employees);
        rsp_success_json([
            'employees' => $employees,
            'session_key' => $session_key,
        ]);
    }

    public function projectsAction()
    {
        if (isTrueKey($this->params, ...['mobile'])) $this->getProjectsByMobile();
        if (isTrueKey($this->params, ...['app_id', 'code'])) $this->getProjectsByCode();
        rsp_error_tips(10001);
    }

    private function getProjectsByMobile()
    {
        $users = $this->user->post('/user/lists', ['mobile' => $this->params['mobile'], 'page' => 1, 'pagesize' => 999]);
        $users = $users['content'] ?? [];
        if (!$users) rsp_success_json([]);
        $user_ids = array_unique(array_filter(array_column($users, 'user_id')));

        $all_devices = $this->user->post('/userdevice/userMergeVisitor', ['user_ids' => $user_ids]);
        $all_devices = ($all_devices['code'] === 0 && $all_devices['content']) ? $all_devices['content'] : [];
        if (!$all_devices) rsp_success_json([]);
        usort($all_devices, function ($a, $b) {
            return ($a['last_use_time'] <=> $b['last_use_time']) * (-1);
        });

        $projects = $this->pm->post('/project/projects', ['project_ids' => array_column($all_devices, 'project_id')]);
        $projects = ($projects['code'] === 0 && $projects['content']) ? many_array_column($projects['content'], 'project_id') : [];
        rsp_success_json($this->projectUnique(array_map(function ($m) use ($projects) {
            return [
                'project_id' => $m['project_id'],
                'project_name' => getArraysOfvalue($projects, $m['project_id'], 'project_name'),
                'client_app_id' => getArraysOfvalue($projects, $m['project_id'], 'app_id'),
            ];
        }, $all_devices)));
    }

    private function getProjectsByCode()
    {
        $openid = $this->wxtoken->get('/wxcode', Comm_Sign::a4wechat_make_sign([
                'app_id' => $this->params['app_id'],
                'code' => $this->params['code']]
        ));
        if ($openid['code'] !== 0) rsp_die_json(10001, $openid['message']);
        $session_key = $openid['content']['session_key'] ?? '';
        $openid = $openid['content']['openid'] ?? '';
        $clients = $this->user->post('/client/lists', ['openid' => $openid, 'page' => 1, 'pagesize' => 999]);
        $clients = $clients['content'] ?? [];
        if (!$clients) rsp_success_json(['projects' => [], 'session_key' => $session_key, 'mobile' => '']);
        $user_ids = array_unique(array_filter(array_column($clients, 'user_id')));
        if (empty($user_ids)) rsp_success_json(['projects' => [], 'session_key' => $session_key, 'mobile' => '']);

        $user = $this->user->post('/user/show', ['user_id' => $user_ids[0]]);
        $user = $user['content'] ?? [];
        if (!$user) rsp_success_json(['projects' => [], 'session_key' => $session_key, 'mobile' => '']);
        $mobile = $user['mobile'];
        $users = $this->user->post('/user/lists', ['mobile' => $mobile, 'page' => 1, 'pagesize' => 999]);
        $users = $users['content'] ?? [];
        if (!$users) rsp_success_json(['projects' => [], 'session_key' => $session_key, 'mobile' => $mobile]);
        $user_ids = array_unique(array_filter(array_column($users, 'user_id')));

        $all_devices = $this->user->post('/userdevice/userMergeVisitor', ['user_ids' => $user_ids]);
        $all_devices = ($all_devices['code'] === 0 && $all_devices['content']) ? $all_devices['content'] : [];
        if (!$all_devices) rsp_success_json(['projects' => [], 'session_key' => $session_key, 'mobile' => $user['mobile']]);
        info(__METHOD__, $all_devices);
        usort($all_devices, function ($a, $b) {
            return ($a['last_use_time'] <=> $b['last_use_time']) * (-1);
        });

        $projects = $this->pm->post('/project/projects', ['project_ids' => array_column($all_devices, 'project_id')]);
        $projects = ($projects['code'] === 0 && $projects['content']) ? many_array_column($projects['content'], 'project_id') : [];
        rsp_success_json([
            'projects' => $this->projectUnique(array_map(function ($m) use ($projects) {
                return [
                    'project_id' => $m['project_id'],
                    'project_name' => getArraysOfvalue($projects, $m['project_id'], 'project_name'),
                    'client_app_id' => getArraysOfvalue($projects, $m['project_id'], 'app_id'),
                ];
            }, $all_devices)),
            'session_key' => $session_key,
            'mobile' => $mobile,
        ]);
    }

    protected function projectUnique($projects)
    {
        $result = [];
        $pushed = [];
        foreach ($projects as $item) {
            if (in_array($item['project_id'], $pushed)) continue;
            $result[] = $item;
            $pushed[] = $item['project_id'];
        }
        return $result;
    }

    protected function checkSign()
    {
        $params = $this->params;

        $sign = $params['sign'];
        unset($params['sign']);
        ksort($params);
        $config = getConfig('other.ini');
        $params['secret'] = $config->client->app_id->secret;

        $str = '';
        foreach ($params as $k => $v) {
            $str .= $k . $v;
        }
        return md5($str) === $sign;
    }
}