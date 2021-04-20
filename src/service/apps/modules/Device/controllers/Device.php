<?php

use Device\ConstantModel as Constant;
use Device\DeviceModel;

class DeviceController extends Yaf_Controller_Abstract
{

    protected $file;

    protected $device;

    protected $face;

    protected $user;

    protected $msg;

    protected $pm;

    protected $resource;

    protected $car;

    protected $redis;

    protected $input = [];

    protected $data = [];

    protected $device_info = [];

    protected $file_info = [];

    protected $event_id = '';

    public function init()
    {
        Yaf_Dispatcher::getInstance()->disableView();

        $this->file = new Comm_Curl(['service'=>'fileupload', 'format'=>'json']);
        $this->device = new Comm_Curl(['service'=>'device', 'format'=>'json']);
        $this->face = new Comm_Curl(['service'=>'face', 'format'=>'json']);
        $this->user = new Comm_Curl(['service'=>'user', 'format'=>'json']);
        $this->msg = new Comm_Curl(['service'=>'msg', 'format'=>'json']);
        $this->pm = new Comm_Curl(['service'=>'pm', 'format'=>'json']);
        $this->resource = new Comm_Curl([ 'service'=>'resource','format'=>'json']);
        $this->car = new Comm_Curl([ 'service'=>'car','format'=>'json']);
    }

    public function msgAction()
    {
        $input = file_get_contents("php://input");
        log_message(__METHOD__ . $input);
        $input = json_decode($input, true);
        $this->input = $input;

        $this->checkInput()->checkDevice()->checkAbility()->uploadFile();

        $handler = 'handle' . ucfirst(strtolower($this->data['object_type']));
        if (!method_exists($this, $handler)) rsp_success_json('该类型设备事件暂不处理');
        $this->hajiaPush();
        $this->$handler();
    }

    private function checkInput()
    {
        if (!$this->input) rsp_die_json(10001, '缺少参数');
        if (!isTrueKey($this->input, ...['device', 'cmd', 'data'])) rsp_die_json(10001, '缺少参数');
        $data = is_array($this->input['data']) ? $this->input['data'] : json_decode($this->input['data'], true);

        if (!isTrueKey($data, ...['object_type'])) rsp_die_json(10001, '缺少参数');
        if (!isTrueKey($data, 'object')) $data['object'] = '';
        if (!isTrueKey($data, 'result')) $data['result'] = '';
        if (!isTrueKey($data, 'image')) $data['image'] = '';

        $this->data = $data;
        return $this;
    }

    private function checkDevice()
    {
        $device = $this->device->post('/device/lists', ['page' => 1, 'pagesize' => 1, 'device_extcode' => $this->input['device']]);
        $device = ($device['code'] === 0 && $device['content']) ? $device['content'][0] : [];
        if (!$device) {
            info(__METHOD__, ['error' => '设备不存在']);
            rsp_die_json(10001, '设备不存在');
        }
        $device_project_info = $this->pm->post('/device/v2/lists', ['device_id' => $device['device_id']]);
        $device['project_id'] = $device_project_info['content'][0]['project_id'] ?? '';
        $device['space_id'] = $device_project_info['content'][0]['space_id'] ?? '';
        if (!$device['project_id']) {
            info(__METHOD__, ['error' => '设备project_id为空', 'device_id' => $device['device_id']]);
            rsp_die_json(10001, '设备project_id为空');
        }

        $this->device_info = $device;
        return $this;
    }

    private function checkAbility()
    {
        $abilities = array_unique(array_filter(array_column($this->device_info['device_ability_tag_ids'], 'device_ability_tag_id')));
        if (!in_array($this->input['cmd'], $abilities)) {
            info(__METHOD__, ['error' => '设备没有该能力', 'cmd' => $this->input['cmd'], 'abilities' => $abilities]);
            rsp_die_json(10001, '设备没有该能力');
        }

        return $this;
    }

    private function uploadFile()
    {
        $file_id = resource_id_generator(Constant::RESOURCE_TYPES['device_event']);
        if (!$file_id) {
            info(__METHOD__, ['error' => '生成文件ID失败']);
            rsp_die_json(10001, '生成文件ID失败');
        }

        $this->event_id = $file_id;
        $this->file_info = [
            'file_id' => $file_id,
        ];

        if (!isTrueKey($this->data, 'image')) return $this;

        $res = $this->file->post('/upload/base64', [
            'base64' => $this->data['image'],
            'resource_type' => 'device_event',
            'resource_id' => $file_id,
        ]);
        if ($res['code'] !== 0) {
            info(__METHOD__, ['error' => '图片上传失败']);
            rsp_die_json(10001, '图片上传失败');
        }

        return $this;
    }

    private function hajiaPush()
    {
        $hajia_push_tags = getConfig('other.ini')->get('hajia_push_tags');
        if ($hajia_push_tags) {
            $hajia_push_tags = explode(',', str_replace([' ', "\n", "\r"], '', $hajia_push_tags));
            $hajia_push_tags = array_filter(array_unique($hajia_push_tags));
        } else {
            $hajia_push_tags = [1397,1265,1266,1267,1268,1269,1270,1271,1272,1273,1274,1275,1276,1277,1278,1279,1280,
                1281,1282,1283,1284,1285,1286,1287,1288,1289];
        }
        if (!$this->input['cmd'] || !in_array($this->input['cmd'], $hajia_push_tags)) {
            return null;
        }
        $data = [
            'device_extcode' => $this->device_info['device_extcode'],
            'cmd' => $this->input['cmd'],
            'device_name' => $this->device_info['device_name'],
            'project_id' => $this->device_info['project_id'] ?? '',
            'space_id' => $this->device_info['space_id'] ?? '',
            'time' => $this->data['time'] ?? date('Y-m-d H:i:s'),
        ];
        Comm_EventTrigger::push('hajia_push', $data);
        return true;
    }

    /*---------------------------------------------  object_type: person(人)   ---------------------------------------*/
    private function handlePerson()
    {
        $tenement_id = ''; $user_id = 0; $tenements = [];
        if ($this->data['object']) {
            $tenements = $this->user->post('/tenement/userlist', ['page' => 1, 'pagesize' => 1, 'user_ids' => [$this->data['object']], 'project_id' => $this->device_info['project_id']]);
            $tenement_id = $tenements['content']['lists'][0]['tenement_id'] ?? '';
        } else {
            $user_id = $this->getUserId();
        }

        $post = [
            'event_id' => $this->event_id,
            'device_id' => $this->device_info['device_id'],
            'tenement_id' => $tenement_id,
            'cmd' => $this->input['cmd'],
            'result' => Constant::EVENT_RESULT_MAP[$this->data['result']] ?? 0,
            'user_id' => $user_id
        ];

        if (isTrueKey($this->data, 'temperature') && intval($this->data['temperature']) > 0) $post['temperature'] = round($this->data['temperature'], 1);
        if (isTrueKey($this->data, 'image')) $post['file_ids'] = [$this->file_info['file_id']];
        $res = $this->device->post('/device/event/add', $post);
        if ($res['code'] !== 0) {
            info(__METHOD__, ['error' => '设备事件添加失败', 'res' => $res]);
            rsp_die_json(10001, '设备事件添加失败');
        }

        //成功时，转发到事件触发器
        if ($post['result'] == 1145 && in_array($this->input['cmd'], Constant::USER_TRAIL_TAG)) {
            $push_data = [
                'device_id' => $this->device_info['device_id'],
                'tenement_id' => $tenement_id,
                'user_id' => $user_id,
                'cmd' => $this->input['cmd'],
            ];
            $result = Comm_EventTrigger::push('user_trail', $push_data);
            if (empty($result)) {
                info(__METHOD__, ['error' => '事件触发器推送失败', 'push_data' => $push_data]);
            }
        }

        //人流进出推送大屏幕
        if ($post['result'] == 1145 && $this->input['cmd'] == 1139) {
            $push_data = [
                'device_id' => $this->device_info['device_id'],
                'project_id' => $this->device_info['project_id'],
                'tenement_identify_tag_id' => $tenements ? $tenements['content']['lists'][0]['tenement_identify_tag_id'] : 0,
                'cmd' => $this->input['cmd'],
                'image' => $this->file_info['file_id']??'',
                'event_time' => time(),
            ];
            $result = Comm_EventTrigger::push('screen_push', [
                'method'=>"personInout",'project_id'=>$this->device_info['project_id'],'data' => json_encode($push_data)
            ]);

            info(__METHOD__, ['msg' => '大屏幕人流进出事件触发器推送', 'push_data' => $push_data, 'result'=>$result]);

        }

        rsp_success_json(1);
    }

    /*---------------------------------------------  object_type: car(车)   -------------------------------------------*/
    private function handleCar()
    {
        $post = [
            'event_id' => $this->event_id,
            'device_id' => $this->device_info['device_id'],
            'plate' => $this->data['object'] ?? '',
            'cmd' => $this->input['cmd'],
            'result' => Constant::EVENT_RESULT_MAP[$this->data['result']] ?? 0,
        ];
        if (isTrueKey($this->data, 'image')) $post['file_ids'] = [$this->file_info['file_id']];
        $res = $this->device->post('/device/event/add', $post);
        if ($res['code'] !== 0) {
            info(__METHOD__, ['error' => '设备事件添加失败', 'res' => $res]);
            rsp_die_json(10001, '设备事件添加失败');
        }

        rsp_success_json(1);
    }

    /*------------------------------------------  object_type: watch(手表)   ------------------------------------------*/
    private function handleWatch()
    {
        $post = [
            'event_id' => $this->event_id,
            'device_id' => $this->device_info['device_id'],
            'attach_id' => $this->data['detail']['uuid'] ?? '',
            'cmd' => $this->input['cmd'],
            'result' => Constant::EVENT_RESULT_MAP[$this->data['result']] ?? 0,
        ];
        $event_time = $this->data['detail']['create_time'] ?? '';
        if ($event_time) $post['event_time'] = strtotime($event_time);
        if (isTrueKey($this->data, 'image')) $post['file_ids'] = [$this->file_info['file_id']];
        $res = $this->device->post('/device/event/add', $post);
        if ($res['code'] !== 0) {
            info(__METHOD__, ['error' => '设备事件添加失败', 'res' => $res]);
            rsp_die_json(10001, '设备事件添加失败');
        }

        rsp_success_json(1);
    }

    /*----------------------------------------  object_type: video(门口机)   ------------------------------------------*/
    private function handleVideo()
    {
        if (!isTrueKey($this->data, 'detail')) {
            rsp_die_json(10001, '缺少detail参数');
        }
        $this->data['detail'] = is_array($this->data['detail']) ? $this->data['detail'] : json_decode($this->data['detail'], true);

        $method = "handleVideo{$this->input['cmd']}";
        if (!method_exists($this, $method)) rsp_success_json(1);
        $this->$method();
    }

    /**
     * 呼叫通知
     */
    private function handleVideo1177()
    {
        if (!isTrueKey($this->data['detail'], ...['callId', 'target'])) {
            rsp_die_json(10001, '缺少detail参数');
        }
        $house_room = $this->data['detail']['target'][0] ?? '';
        if (!$house_room) {
            rsp_die_json(10001, 'detail target不能为空');
        }
        $project = $this->pm->post('/project/projects', ['project_id' => $this->device_info['project_id']]);
        $project = ($project['code'] === 0 && $project['content']) ? $project['content'][0] : [];
        if (!$project) {
            info(__METHOD__, ['error' => '设备项目不存在', 'device_id' => $this->device_info['device_id']]);
            rsp_die_json(10001, '设备项目不存在');
        }

        $keyword2 = $project['project_name'];
        $space_branch = $this->pm->post('/space/branch', ['space_id' => $this->device_info['space_id']]);
        $space_branch = ($space_branch['code'] === 0 && $space_branch['content']) ? $space_branch['content'] : [];
        usort($space_branch, function ($a, $b) {
            return $a['space_type'] <=> $b['space_type'];
        });
        foreach ($space_branch ?: [] as $item) {
            if ($item['space_type'] === 244) {
                $keyword2 .= "{$item['space_name']}栋";
            }
            if ($item['space_type'] === 1392) {
                $keyword2 .= "{$item['space_name']}单元";
            }
        }


        $space_children = $this->pm->post('/space/children', ['space_id' => $this->device_info['space_id']]);
        $space_children = ($space_children['code'] === 0 && $space_children['content']) ? $space_children['content'] : [];
        $space_children_ids = array_unique(array_filter(array_column($space_children, 'space_id')));

        // 房产
        $post = [
            'space_ids' => $space_children_ids,
            'full_house_room' => $house_room,
        ];
        $house_ids = $this->pm->post('/house/basic/lists', $post);
        $house_ids = ($house_ids['code'] === 0 && $house_ids['content']) ? $house_ids['content'] : [];
        if (!$house_ids) {
            info(__METHOD__, ['error' => '查询房产失败', 'device_id' => $this->device_info['device_id']]);
            rsp_die_json(10001, '查询房产失败');
        }
        if (count($house_ids) > 1) {
            info(__METHOD__, ['error' => '查询到多个房产', 'device_id' => $this->device_info['device_id']]);
            rsp_die_json(10001, '查询到多个房产');
        }
        $house_id = $house_ids[0]['house_id'];

        $house_tenements = $this->user->post('/house/lists', ['house_ids' => [$house_id]]);
        $house_tenements = ($house_tenements['code'] === 0 && $house_tenements['content']) ? $house_tenements['content'] : [];
        if (!$house_tenements) {
            info(__METHOD__, ['error' => '房产没有住户', 'device_id' => $this->device_info['device_id'], 'house_id' => $house_id]);
            rsp_die_json(10001, '房产没有住户');
        }

        // 推送住户
        $house_916_tenements = $house_917_tenements = [];
        foreach ($house_tenements ?: [] as $item) {
            $push_to = "house_{$item['tenement_identify_tag_id']}_tenements";
            array_push($$push_to, $item);
        }
        $house_916_tenements = array_filter(array_map(function ($m) {
            if ($m['out_time'] > 0 && $m['out_time'] < time()) return null;
            if ($m['tenement_house_status'] === 'N') return null;
            return $m;
        }, $house_916_tenements));
        $unmoved_away_house_917_tenement_ids = [];
        foreach ($house_917_tenements ?: [] as $item) {
            if (!($item['out_time'] > 0 && $item['out_time'] < time())) {
                if ($item['tenement_house_status'] === 'N') continue;
                $unmoved_away_house_917_tenement_ids[] = $item['tenement_id'];
            }
        }
        $choose_tenement_ids = $unmoved_away_house_917_tenement_ids ?: array_unique(array_filter(array_column($house_916_tenements, 'tenement_id')));
        if (!$choose_tenement_ids) {
            info(__METHOD__, ['error' => '房产没有可推送的住户', 'device_id' => $this->device_info['device_id'], 'house_id' => $house_id]);
            rsp_die_json(10001, '房产没有可推送的住户');
        }

        // user_ids
        $user_ids = $this->user->post('/tenement/userlist', ['tenement_ids' => $choose_tenement_ids]);
        $user_ids = ($user_ids['code'] === 0 && $user_ids['content']) ? $user_ids['content']['lists'] : [];
        if (!$user_ids) {
            info(__METHOD__, ['error' => '查询users失败', 'tenement_ids' => $choose_tenement_ids]);
            rsp_die_json(10001, '查询users失败');
        }
        $user_ids = array_unique(array_filter(array_column($user_ids, 'user_id')));

        // open_ids
        $post = [
            'user_ids' => $user_ids,
            'client_app_id' => 'uNoilxyVl7fO0uMKKqCP',
            'page' => 1,
            'pagesize' => count($user_ids),
        ];
        $config = getConfig('other.ini');
        $post['app_id'] = $config->device->message->wechat;
        $open_ids = $this->user->post('/client/lists', $post);
        $open_ids = ($open_ids['code'] === 0 && $open_ids['content']) ? $open_ids['content'] : [];
        if (!$open_ids) {
            info(__METHOD__, ['error' => '查询open_ids失败', 'user_ids' => $user_ids]);
            rsp_die_json(10001, '查询open_ids失败');
        }
        $open_ids = array_unique(array_filter(array_column($open_ids, 'openid')));

        // resource lite
        $resource_lite = $this->resource->post('/resource/id/lite', ['resource_id' => $this->device_info['device_id']]);
        $resource_lite = $resource_lite['content'] ?? '';

        // push message
        $time = time();
        $date = date('Y年m月d日 H:i:s', $time);
        $expire_time = $time + 30;
        $wx_miniprogram_appid = 'wxedb5cd88641f3472';
        $url_params = [
            'flag' => 'digitalCommunication',
            'callId' => $this->data['detail']['callId'],
            'device_id' => $this->device_info['device_id'],
            'resource_lite' => $resource_lite,
            'expire_time' => $expire_time,
        ];
        $wx_miniprogram_pagepath = "pages/index/index?" . http_build_query($url_params);
        foreach ($open_ids ?: [] as $open_id) {
            $post = [
                'channel' => ['wechat'],
                'title' => '呼叫通知',
                'source' => 'device_call',
                'open_id' => $open_id,
                'wx_template_id' => 45,
                'wx_params' => json_encode([
                    'first' => [
                        'value' => '您有一条呼叫通知',
                        'color' => '#173177',
                    ],
                    'keyword1' => [
                        'value' => $date,
                        'color' => '#173177',
                    ],
                    'keyword2' => [
                        'value' => $keyword2,
                        'color' => '#173177',
                    ],
                    'remark' => [
                        'value' => '点击接听',
                        'color' => '#173177',
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'wx_miniprogram' => json_encode([
                    'appid' => $wx_miniprogram_appid,
                    'pagepath' => $wx_miniprogram_pagepath,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
            $sent = $this->msg->post('/pushmsg/singleUser', $post);
            info(__METHOD__, ['pushmsg' => $post, 'sent' => $sent]);
        }

        rsp_success_json(1);
    }

    /**
     * 接听呼叫
     */
    private function handleVideo1173()
    {
        $redis = \Comm_Redis::getInstance();
        $key = getConfig('redis.ini')->redis->list->keys->call_message;
        $redis->lpush($key, json_encode([
            'device_id' => $this->device_info['device_id'],
            'cmd' => 1173,
            'data' => [
                'callId' => $this->data['detail']['callId'],
            ],
        ]));
        $res = $redis->setex($key . $this->data['detail']['callId'], 120, 1);
        info(__METHOD__, ['handleVideo1173_set_redis' => $res]);
        rsp_success_json(1);
    }

    /**
     * 挂断呼叫
     */
    private function handleVideo1175()
    {
        $redis = \Comm_Redis::getInstance();
        $key = getConfig('redis.ini')->redis->list->keys->call_message;
        $redis->lpush($key, json_encode([
            'device_id' => $this->device_info['device_id'],
            'cmd' => 1175,
            'data' => [
                'callId' => $this->data['detail']['callId'],
            ],
        ]));
        rsp_success_json(1);
    }

    /**
     * 在线开锁
     */
    private function handleVideo921()
    {
        $redis = \Comm_Redis::getInstance();
        $key = getConfig('redis.ini')->redis->list->keys->call_message;
        $redis->lpush($key, json_encode([
            'device_id' => $this->device_info['device_id'],
            'cmd' => 921,
            'data' => [
                'callId' => $this->data['detail']['callId'],
            ],
        ]));
        rsp_success_json(1);
    }

    private function getUserId()
    {
        $url = $this->fileInfo($this->file_info['file_id']);
        if (!$url) {
            return false;
        }

        $params = ['url' => $url, 'group_ids' => [$this->device_info['project_id']]];
        $person = $this->face->post('/person/search', $params);
        if ($person['code'] == 0 && !empty($person['content'])) {
            $user_id = substr($person['content'][0]['person_id'], 0, -24);
        } else {
            $this->setAppId();
            $temp_mobile = '20' . rand(100000000, 999999999);
            $res = $this->user->post('/user/add', ['mobile' => $temp_mobile]);
            $user_id = $res['code'] == 0 ? $res['content'] : '';
            if ($user_id) {
                //添加人员
                $params = [
                    'person_id' => $user_id . $this->device_info['project_id'],
                    'project_id' => $this->device_info['project_id'],
                    'file_id' => $this->file_info['file_id'],
                    'url' => $url
                ];
                $res = $this->face->post('/person/create', $params);
                info(__METHOD__ . "---创建人员-", ['res' => $res, 'params' => $params]);
            }
        }

        return $user_id;
    }

    /**
     * 获取文件信息
     */
    private function fileInfo($file_id)
    {
        $result = $this->file->post('/info', ['file_id' => $file_id]);
        if ($result['code'] != 0 || empty($result['content'])) {
            info(__METHOD__ . "---获取人脸图片信息失败-", ['res' => $result, 'file_id' => $file_id]);
            return false;
        }

        return $result['content']['url'];
    }

    private function setAppId()
    {
        $project = $this->pm->post('/project/projects', ['project_id' => $this->device_info['project_id']]);
        if ($project['code'] == 0 && !empty($project['content'])) {
            $_SESSION['oauth_app_id'] = $project['content'][0]['app_id'];
        }
    }

    /*-----------------------------------------  object_type: doorSensor(门磁)   --------------------------------------*/
    private function handleDoorSensor()
    {
        $post = [
            'event_id' => $this->event_id,
            'device_id' => $this->device_info['device_id'],
            'cmd' => $this->input['cmd'],
            'result' => Constant::EVENT_RESULT_MAP[$this->data['result']] ?? 0,
        ];
        $event_time = $this->data['time'] ?? '';
        if ($event_time) $post['event_time'] = strtotime($event_time);
        if (isTrueKey($this->data, 'power')) $post['power'] = $this->data['power'];
        $res = $this->device->post('/device/event/add', $post);
        if ($res['code'] !== 0) {
            info(__METHOD__, ['error' => '设备事件添加失败', 'res' => $res]);
            rsp_die_json(10001, '设备事件添加失败');
        }

        $this->pushScreen('门磁');

        rsp_success_json(1);
    }

    /*----------------------------------------  object_type: highAltitude(高空抛物)   ----------------------------------*/
    private function handleHighAltitude(){
        $this->data['camera_info']['groupName'] = $this->data['groupName'] ?? '';
        $post = [
            'event_id' => $this->event_id,
            'device_id' => $this->device_info['device_id'],
            'cmd' => $this->input['cmd'],
            'result' => Constant::EVENT_RESULT_MAP[$this->data['result']] ?? 0,
            'event_time' => $this->data['eventTime'],
            'attach_id' => json_encode($this->data['camera_info'])
        ];
        $res = $this->device->post('/device/event/add', $post);
        log_message('------HighAltitude------'.json_encode([$post,$res]));
        if ($res['code'] !== 0) {
            info(__METHOD__, ['error' => '高空抛物设备事件添加失败', 'res' => $res]);
            rsp_die_json(10001, '高空抛物设备事件添加失败');
        }

        (new DeviceModel())->createHighAltitudeWorkBook($this->input,$this->device_info);

        $this->pushScreen('高空抛物');

        rsp_success_json(1);
    }

    /*-----------------------------------------  object_type: ammeter(电表)   -----------------------------------------*/
    private function handleAmmeter()
    {
        $post = [
            'event_id' => $this->event_id,
            'device_id' => $this->device_info['device_id'],
            'cmd' => $this->input['cmd'],
            'result' => Constant::EVENT_RESULT_MAP[$this->data['result']] ?? 0,
        ];
        $post['attach_id'] = is_array($this->data['detail']) ? json_encode($this->data['detail'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $this->data['detail'];
        $res = $this->device->post('/device/event/add', $post);
        if ($res['code'] !== 0) {
            info(__METHOD__, ['error' => '设备事件添加失败', 'res' => $res]);
            rsp_die_json(10001, '设备事件添加失败');
        }

        rsp_success_json(1);
    }

    /*------------------------------------------  object_type: lock(车位锁)   -----------------------------------------*/
    private function handleLock()
    {
        $post = [
            'event_id' => $this->event_id,
            'device_id' => $this->device_info['device_id'],
            'cmd' => $this->input['cmd'],
            'result' => Constant::EVENT_RESULT_MAP[$this->data['result']] ?? 0,
        ];
        $post['attach_id'] = is_array($this->data['detail']) ? json_encode($this->data['detail'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $this->data['detail'];
        $res = $this->device->post('/device/event/add', $post);
        if ($res['code'] !== 0) {
            info(__METHOD__, ['error' => '设备事件添加失败', 'res' => $res]);
            rsp_die_json(10001, '设备事件添加失败');
        }

        rsp_success_json(1);
    }

    /*------------------------------------------  object_type: xjt(消检通告警)   -----------------------------------------*/
    private function handleXjt()
    {
        $post = [
            'event_id' => $this->event_id,
            'device_id' => $this->device_info['device_id'],
            'cmd' => $this->input['cmd'],
            'result' => Constant::EVENT_RESULT_MAP[$this->data['result']] ?? 0,
        ];
        $res = $this->device->post('/device/event/add', $post);
        if ($res['code'] !== 0) {
            info(__METHOD__, ['error' => '设备事件添加失败', 'res' => $res]);
            rsp_die_json(10001, '设备事件添加失败');
        }

        $this->pushScreen('消检通');

        rsp_success_json(1);
    }

    private function pushScreen($tag){
        $event_time = $this->data['time'] ?? '';
        $event_time = $event_time ? $event_time : time();
        $level = 1;
        if(in_array($this->input['cmd'],[1278,1279,1280])){
            $level = 2;
        }elseif (in_array($this->input['cmd'],[1395,1396,1397,1398])){
            $level = 3;
        }
        $push_data = [
            'device_id' => $this->device_info['device_id'],
            'project_id' => $this->device_info['project_id'],
            'cmd' => $this->input['cmd'],
            'event_type' => (new DeviceModel())->getTagName($this->input['cmd']),
            'image' => $this->file_info['file_id']??'',
            'event_time' => $event_time,
            'space_name' => (new DeviceModel())->getGroupName($this->device_info['space_id']),
            'event_level' => $level
        ];
        $result = Comm_EventTrigger::push('screen_push', [
            'method'=>"warning",'project_id'=>$this->device_info['project_id'],'data' => json_encode($push_data)
        ]);
        if (empty($result)) {
            info(__METHOD__, ['error' => '大屏幕'.$tag.'告警事件触发器推送失败', 'push_data' => $push_data]);
        }
    }

}
