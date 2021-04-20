<?php

namespace Device;

class DeviceModel
{

    protected $user;

    protected $pm;

    protected $device;

    protected $file;

    protected $wos;

    protected $tiding;

    protected $tag;

    public function __construct()
    {
        $this->user = new \Comm_Curl(['service' => 'user', 'format' => 'json']);
        $this->pm = new \Comm_Curl(['service' => 'pm', 'format' => 'json']);
        $this->device = new \Comm_Curl(['service' => 'device', 'format' => 'json']);
        $this->file = new \Comm_Curl(['service' => 'fileupload', 'format' => 'json']);
        $this->wos = new \Comm_Curl(['service' => 'wos', 'format' => 'json', 'header' => ["Content-Type: application/json"]]);
        $this->tiding = new \Comm_Curl(['service' => 'tiding', 'format' => 'json']);
        $this->tag = new \Comm_Curl(['service' => 'tag', 'format' => 'json']);
    }

    public function toggleTenementPrivileges($tenement_id = '')
    {
        return true;
    }

    public function createHighAltitudeWorkBook($data, $device)
    {
        $project = $this->getProject(['project_id' => $device['project_id']]);
        if (!$project) return false;
        log_message('=========HighAltitude==$project==========' . json_encode($project));
        log_message('=========HighAltitude==$device==========' . json_encode($device));
        $app_id = $project['app_id'];
        $config = getConfig('other.ini');
        $operator = $config->get('highatd.operator.system.' . $app_id); //发起人 system
        if (!$operator) {
            log_message('----------device/HighAltitude-----缺少工单发起人配置 highatd.operator.system.' . $app_id);
            return false;
        }
        $performer = $this->getEmployeeFromJob(
            $device['project_id'],
            $device['space_id'],
            $config->get('highatd.performer.job')
        ); //执行人 现阶段默认1245安全巡查管家；
        
        if (!$performer) {
            log_message('----------device/HighAltitude-----缺少工单执行人 ');
            return false;
        }
        $audience = $this->getEmployeeFromJob(
            $device['project_id'],
            $device['space_id'],
            $config->get('highatd.audience.job')
        );//关注人  默认1242,1243,1244,1245,1256,1257

        $group_name = (isset($data['data']['groupName']) && !empty($data['data']['groupName'])) ?
            $data['data']['groupName'] :
            $this->getGroupName($device['space_id']);

        $title = "{$project['project_name']}-高空抛物-" . date('Y-m-d H:i', $data['data']['eventTime'] ?? time());
        $content = "{$device['device_name']} 高空抛物监控设备，在{$group_name}，发现有高空抛物的情况，需要相关人员前去查看并处理；";
        $params = [
            'kind' => [
                1,
                92
            ],
            'multipart' => [
                'title' => $title,
                'priority' => 616, //优先级 高
                'content' => $content,
                'project' => [
                    'id' => $project['project_id'],
                    'name' => $project['project_name'],
                    'address' => ''
                ],
                'pigs' => json_encode($data['data']['camera_info']['pigs']),
                'videos' => json_encode($data['data']['camera_info']['videos']),
            ],
            'source' => 617,
            'subapp' => 22,
            'visibility' => 'private',
            'operator' => $operator, //发起人
            'from_id' => $project['admin_app_id']
        ];

        if ($audience) {
            $params['audience'] = json_encode([
                's' => $audience//关注人
            ]);
        }

        if ($performer) {
            $params['performer'] = json_encode([
                's' => $performer//执行人
            ]);
        }
        $_SESSION['oauth_app_id'] = $app_id;
        $result = $this->wos->post('/create', json_encode($params));
        log_message('----------device/HighAltitude-----创建高空抛物工单------===' . json_encode($result, JSON_UNESCAPED_UNICODE));
        if ($result['code'] == 0 && !empty($result['content'])) {
            $this->publish($result['content']);
            //推送消息
            $this->pushTiding($result['content']);
        }
    }

    private function publish($data)
    {
        $params = [
            '_id' => $data['_id'],
            'operator' => $data['initiator'],
            '__v' => $data['__v']
        ];
        $result = $this->wos->post('/publish', json_encode($params));
        log_message('----------device/HighAltitude-----发布高空抛物工单------===' . json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    private function pushTiding($wb_result)
    {
        $push_data = [
            'sid' => $wb_result['_id'],
            'kind' => 'change_order',
            'title' => $wb_result['title'],
            'initiator' => $wb_result['initiator'],
            'audience' => json_encode($wb_result['audience']),
            'details' => $wb_result['details']['content']
        ];
        $this->tiding->post('/tiding/add', $push_data);
    }

    private function getProject($params = [])
    {
        if (empty($params)) return [];
        $fields = ['fields' => ['project_id', 'project_name', 'app_id', 'admin_app_id']];
        $project = $this->pm->post('/project/projects', array_merge($params, $fields));
        if (!$project || $project['code'] != 0 || !$project['content']) {
            log_message('----------device/HighAltitude-----项目详情查询失败------' . json_encode([$project, $params]));
            rsp_die_json(10001, '高空抛物工单：项目详情查询失败');
        }
        return $project['content'][0];
    }

    private function getEmployeeFromJob($project_id, $space_id, $job_tag_id)
    {
        if (!$project_id || !$job_tag_id || !$space_id) return '';
        $employee_id = [];
        $job_tag_ids = explode(',', $job_tag_id);
        foreach ($job_tag_ids as $val) {
            $id = $this->getJobEly(['job_tag_id' => $val, 'space_id' => $space_id, 'project_id' => $project_id]);
            if ($id) {
                $employee_id[] = $id;
            }
        }
        log_message('-----------------device/HighAltitude-----岗位对应员工信息------' .
            json_encode([$job_tag_id => $employee_id]));
        return count($employee_id) > 1 ? $employee_id : ($employee_id[0] ?? '');
    }

    private function getJobEly($params)
    {
        $argv = [
            'status_tag_id' => 1298,
            'job_name_tag_id' => $params['job_tag_id'],
            'space_id' => $params['space_id']
        ];
        $job = $this->pm->post('/job/simpleLists', $argv);
        if ($job['code'] != 0 || empty($job['content'])) {
            log_message('-----------------device/HighAltitude-----通过岗位标签查询岗位信息失败(/job/simpleLists)------' .
                json_encode([$argv, $job]));
            return false;
        }

        $job_ids = array_unique(array_filter(array_column($job['content'], 'job_id')));

        $argv = [
            'job_ids' => $job_ids,
            'page' => 1,
            'pagesize' => 99
        ];
        $employee_job = $this->user->post('/employeejob/lists', $argv);
        if ($employee_job['code'] != 0 || empty($employee_job['content'])) {
            log_message('-----------------device/HighAltitude-----通过岗位查询员工信息失败(/employeejob/lists)------' .
                json_encode([$argv, $employee_job]));
            return false;
        }

        $employee_ids = array_unique(array_filter(array_column($employee_job['content'], 'employee_id')));

        $argv = [
            'project_id' => $params['project_id'],
            'employee_ids' => $employee_ids,
        ];
        $employee = $this->user->post('/employee/userlist', $argv);
        if ($employee['code'] != 0 || empty($employee['content']['lists'])) {
            log_message('-------------device/HighAltitude-----查询员工信息失败(/employee/userlist)------' .
                json_encode([$argv, $employee]));
            return false;
        }

        return $employee['content']['lists'][0]['employee_id'];
    }

    public function getGroupName($space_id)
    {
        $space_branches = $this->pm->post('/space/branch', ['space_id' => [$space_id]]);
        $space_branches = $space_branches['content'] ?? [];

        $branch_info = (new \Project\SpaceModel())->parseBranch($space_branches);
        $space_name_full = $branch_info['space_name_full'] ?? '';
        return $space_name_full;
    }

    public function getTagName($tag_id){
        if(!$tag_id) return '';
        $tag = $this->tag->post('/tag/show', ['tag_id' => $tag_id]);
        $tag_name = ($tag['code'] == 0 && !empty($tag['content'])) ? $tag['content']['tag_name'] : '';
        return $tag_name;
    }

}