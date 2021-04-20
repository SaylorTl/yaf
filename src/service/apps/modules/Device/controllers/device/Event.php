<?php

use Device\ConstantModel as Constant;
use Project\SpaceModel;

final class Event extends Base
{
    public function lists($params = [])
    {
        if (!isTrueKey($params, 'page', 'pagesize')) rsp_die_json(10001, 'page pagesize 参数缺失或错误');

        // pm device_ids
        $where = [
            'project_id' => $this->project_id,
        ];
        if (isTrueKey($params, 'space_id')) $where['space_id'] = $params['space_id'];
        $pm_device_ids = $this->pm->post('/device/v2/lists', $where);
        $pm_device_ids = ($pm_device_ids['code'] === 0 && $pm_device_ids['content']) ? $pm_device_ids['content'] : [];
        if (!$pm_device_ids) rsp_success_json(['total' => 0, 'lists' => []]);
        $device_space_ids = many_array_column($pm_device_ids, 'device_id');
        $pm_device_ids = array_unique(array_filter(array_column($pm_device_ids, 'device_id')));

        // device device_ids
        $device_device_ids = $where = [];
        if (is_not_empty($params, 'device_name')) $where['device_name'] = $params['device_name'];
        if (isTrueKey($params, 'device_type_tag_id')) $where['device_type_tag_id'] = $params['device_type_tag_id'];
        if ($where) {
            $device_device_ids = $this->device->post('/device/ids', $where);
            $device_device_ids = ($device_device_ids['code'] === 0 && $device_device_ids['content']) ? $device_device_ids['content'] : [];
            if (!$device_device_ids) rsp_success_json(['total' => 0, 'lists' => []]);
            $device_device_ids = array_values(array_unique(array_filter(array_column($device_device_ids, 'device_id'))));
        }

        // final device_ids
        $final_device_ids = $device_device_ids ? array_intersect($pm_device_ids, $device_device_ids) : $pm_device_ids;
        if (!$final_device_ids) rsp_success_json(['total' => 0, 'lists' => []]);

        // tenement_ids
        $tenement_ids = $where = [];
        if (is_not_empty($params, 'real_name')) $where['real_name'] = $params['real_name'];
        if (is_not_empty($params, 'mobile')) $where['mobile'] = $params['mobile'];
        if ($where) {
            $tenement_ids = $this->user->post('/tenement/userlist',array_merge($where, ['page' => 1, 'pagesize' => 99999]));
            $tenement_ids = ($tenement_ids['code'] === 0 && $tenement_ids['content']) ? $tenement_ids['content']['lists'] : [];
            if (!$tenement_ids) rsp_success_json(['total' => 0, 'lists' => []]);
            $tenement_ids = array_unique(array_filter(array_column($tenement_ids, 'tenement_id')));
        }

        // lists
        $where = [
            'page' => $params['page'],
            'pagesize' => $params['pagesize'],
        ];
        $where['device_ids'] = $final_device_ids;
        if ($tenement_ids) $where['tenement_ids'] = $tenement_ids;
        if (isTrueKey($params, 'tenement_identify_tag_id')) $where['tenement_identify_tag_id'] = $params['tenement_identify_tag_id'];
        if (isTrueKey($params, 'creationtime_begin')) $where['creationtime_begin'] = $params['creationtime_begin'];
        if (isTrueKey($params, 'creationtime_end')) $where['creationtime_end'] = $params['creationtime_end'];
        if (isTrueKey($params, 'result')) $where['result'] = $params['result'];
        if (isTrueKey($params, 'cmd')) $where['cmd'] = $params['cmd'];
        if (is_not_empty($params, 'plate')) $where['plate'] = $params['plate'];

        $lists = $this->device->post('/device/event/lists', $where);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        //total
        unset($where['page'], $where['pagesize']);
        $total = $this->device->post('/device/event/count', $where);
        if ($total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        // device info
        $device_ids = array_unique(array_filter(array_column($lists['content'], 'device_id')));
        $devices = $this->device->post('/device/devices', ['device_ids' => $device_ids]);
        $devices = ($devices['code'] === 0 && $devices['content']) ? many_array_column($devices['content'], 'device_id') : [];

        // device project info
        $device_projects = $this->pm->post('/device/v2/lists', ['device_ids' => $device_ids]);
        $device_projects = ($device_projects['code'] === 0 && $device_projects['content']) ? many_array_column($device_projects['content'], 'device_id') : [];

        // space
        $space_ids = array_unique(array_filter(array_column($device_projects, 'space_id')));
        $space_branches = $this->pm->post('/space/batchBranch', ['space_ids' => $space_ids]);
        $space_branches = $space_branches['content'] ?? [];

        // tenement info
        $tenements = [];
        $tenement_ids = array_unique(array_filter(array_column($lists['content'], 'tenement_id')));
        if ($tenement_ids) {
            $tenements = $this->user->post('/tenement/userlist', ['page' => 1, 'pagesize' => $params['pagesize'], 'tenement_ids' => $tenement_ids]);
            $tenements = ($tenements['code'] === 0 && $tenements['content']) ? many_array_column($tenements['content']['lists'], 'tenement_id') : [];
        }

        // resource lites
        $resource_lites = $this->resource->post('/resource/id/lite',['resource_ids' => array_filter(array_merge($device_ids, array_unique(array_column($device_projects, 'project_id'))))]);
        $resource_lites = ($resource_lites['code'] === 0 && $resource_lites['content']) ? many_array_column($resource_lites['content'], 'resource_id') : [];

        $data = array_map(function ($m) use ($device_space_ids, $devices, $tenements, $device_projects, $space_branches, $resource_lites) {
            $m['project_id'] = getArraysOfvalue($device_projects, $m['device_id'], 'project_id');
            $m['space_id'] = getArraysOfvalue($device_space_ids, $m['device_id'], 'space_id');
            $m['device_name'] = getArraysOfvalue($devices, $m['device_id'], 'device_name');
            $m['device_type_tag_id'] = getArraysOfvalue($devices, $m['device_id'], 'device_type_tag_id');
            $m['real_name'] = getArraysOfvalue($tenements, $m['tenement_id'], 'real_name');
            $m['mobile'] = getArraysOfvalue($tenements, $m['tenement_id'], 'mobile');
            $m['event_time'] = $m['event_time'] ? date('Y-m-d H:i:s', $m['event_time']) : '';
            $branch_info = SpaceModel::parseBranch($space_branches[$m['space_id']] ?? []);
            $m['space_name_full'] = $branch_info['space_name_full'] ?? '';
            $m['attach'] = isTrueKey($m, 'attach_id') ? json_decode($m['attach_id'],true) : [];
            $m['device_lite'] = getArraysOfvalue($resource_lites, $m['device_id'], 'resource_lite');
            $m['project_lite'] = getArraysOfvalue($resource_lites, $m['project_id'], 'resource_lite');
            $m['pigs'] = $m['attach']['pigs'] ?? [];
            $m['videos'] = $m['attach']['videos'] ?? [];
            $m['group_name'] =  $m['attach']['groupName'] ?? '';
            return $m;
        }, $lists['content']);

        rsp_success_json(['total' => (int)$total['content'], 'lists' => $data]);
    }

    public function update($params = [])
    {
        if (!isTrueKey($params, ...['event_id'])) rsp_error_tips(10001);

        $event = $this->device->post('/device/event/lists', ['page' => 1, 'pagesize' => 1, 'event_id' => $params['event_id']]);
        $event = ($event['code'] === 0 && $event['content']) ? $event['content'][0] : [];
        if (!$event) rsp_error_tips(10008);
        if ($event['tenement_id'] && $event['tenement_identify_tag_id']) rsp_success_json(1, '无需修正');

        $device = $this->pm->post('/device/v2/lists',['page' => 1, 'pagesize' => 1, 'device_id' => $event['device_id']]);
        $device = ($device['code'] === 0 && $device['content']) ? $device['content'][0] : [];
        if (!$device) rsp_error_tips(10002, '设备不存在');

        if (!isTrueKey($event, 'file_ids')) rsp_error_tips(10002, '事件图片');
        $file = $this->file->post('/info', ['file_id' => $event['file_ids'][0]]);
        $file = ($file['code'] === 0 && $file['content']) ? $file['content'] : [];
        if (!$file) rsp_error_tips(10002, '设备事件图片');

        $persons = $this->face->post('/person/search', ['url' => $file['url']]);
        $persons = ($persons['code'] === 0 && $persons['content']) ? $persons['content'] : [];
        if (!$persons) rsp_success_json(1, '人脸库未匹配到数据');

        $person = [];
        foreach ($persons as $item) {
            if ($item['group_id'] === $device['project_id']) {
                $person = $item;
                break;
            }
        }
        if (!$person) rsp_success_json(1, '人脸库未匹配到数据');
        $tenements = $this->user->post('/tenement/lists', ['user_id' => substr($person['person_id'], 0, strpos($person['person_id'], $person['group_id'])), 'project_id' => $person['group_id'], 'page' => 1, 'pagesize' => 1]);
        $tenements = ($tenements['code'] === 0 && $tenements['content']) ? $tenements['content'] : [];
        $tenement_id = $tenements[0]['tenement_id'] ?? '';
        if (!$tenement_id) rsp_success_json(1, '该住户不存在');

        // device houses
        $post = [
            'project_id' => $device['project_id'],
        ];
        if ($device['space_id']) {
            $spaces = $this->pm->post('/space/children',['space_id' => $device['space_id']]);
            $spaces = ($spaces['code'] === 0 && $spaces['content']) ? $spaces['content'] : [];
            if (!$spaces) rsp_error_tips(10002, '设备space_id错误');
            $post['space_ids'] = array_unique(array_filter(array_column($spaces, 'space_id')));
        }
        $house_ids = $this->pm->post('/house/basic/lists', $post);
        $house_ids = ($house_ids['code'] === 0 && $house_ids['content']) ? $house_ids['content'] : [];
        if (!$house_ids) rsp_error_tips(10002, '设备房产不存在');
        $house_ids = array_unique(array_filter(array_column($house_ids, 'house_id')));

        // tenement houses
        $post = [
            'tenement_ids' => [$tenement_id],
            'house_ids' => $house_ids,
        ];
        $tenement_houses = $this->user->post('/house/lists', $post);
        $tenement_houses = ($tenement_houses['code'] === 0 && $tenement_houses['content']) ? $tenement_houses['content'] : [];
        if (!$tenement_houses) rsp_error_tips(10002, '住户房产不存在');

        $this->device->post('/device/event/update', [
            'event_id' => $params['event_id'],
            'tenement_id' => $tenement_id,
            'tenement_identify_tag_id' => $tenement_houses[0]['tenement_identify_tag_id'],
        ]);

        rsp_success_json(1, '修正成功');
    }

    public function images($params = [])
    {
        if (!isTrueKey($params, 'device_id')) rsp_die_json(10001, 'device_id 参数缺失');
        // lists
        $where = [
            'page' => 1,
            'pagesize' => 3,
            'device_id' => $params['device_id'],
        ];

        $lists = $this->device->post('/device/event/lists', $where);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json([]);
        $file_ids = [];
        foreach ($lists['content'] ?: [] as $item) $file_ids[] = $item['file_ids'][0];
        rsp_success_json($file_ids);
    }

    public function add($params = [])
    {
        if (!isTrueKey($params, ...['device_id', 'cmd'])) rsp_error_tips(10001);
        if (!isTrueKey($params, 'tenement_id') && !isTrueKey($params, 'visit_id')) rsp_error_tips(10001);

        $method = '';
        if (isTrueKey($params, 'tenement_id')) $method = 'addTenement';
        if (isTrueKey($params, 'visit_id')) $method = 'addVisitor';
        if (!$method) rsp_error_tips(10001);

        $this->$method($params);
    }

    private function addTenement($params = [])
    {
        $device = $this->device->post('/device/lists', ['page' => 1, 'pagesize' => 1, 'device_id' => $params['device_id']]);
        $device = ($device['code'] === 0 && $device['content']) ? $device['content'][0] : [];
        if (!$device) rsp_error_tips(10002, '设备');

        $abilities = array_unique(array_filter(array_column($device['device_ability_tag_ids'], 'device_ability_tag_id')));
        if (!in_array($params['cmd'], $abilities)) rsp_error_tips(10002, '设备能力');

        $device = $this->pm->post('/device/v2/lists', ['page' => 1, 'pagesize' => 1, 'device_id' => $params['device_id']]);
        $device = ($device['code'] === 0 && $device['content']) ? $device['content'][0] : [];
        if (!$device) rsp_error_tips(10002, '设备');
        $project_id = $device['project_id'];

        $event_id = resource_id_generator(Constant::RESOURCE_TYPES['device_event']);
        if (!$event_id) rsp_die_json(10001, '生成事件ID失败');

        $tenement_houses = [];

        // device houses
        $post = [
            'project_id' => $device['project_id'],
        ];
        if ($device['space_id']) {
            $spaces = $this->pm->post('/space/children',['space_id' => $device['space_id']]);
            $spaces = ($spaces['code'] === 0 && $spaces['content']) ? $spaces['content'] : [];
            if (!$spaces) rsp_error_tips(10002, '设备space_id错误');
            $post['space_ids'] = array_unique(array_filter(array_column($spaces, 'space_id')));
        }
        $house_ids = $this->pm->post('/house/basic/lists', $post);
        $house_ids = ($house_ids['code'] === 0 && $house_ids['content']) ? $house_ids['content'] : [];
        $house_ids = array_unique(array_filter(array_column($house_ids, 'house_id')));

        // tenement houses
        if ($house_ids) {
            $post = [
                'tenement_ids' => [$params['tenement_id']],
                'house_ids' => $house_ids,
            ];
            $tenement_houses = $this->user->post('/house/lists', $post);
            $tenement_houses = ($tenement_houses['code'] === 0 && $tenement_houses['content']) ? $tenement_houses['content'] : [];
        }

        $post = [
            'event_id' => $event_id,
            'device_id' => $params['device_id'],
            'tenement_id' => $params['tenement_id'],
            'tenement_identify_tag_id' => $tenement_houses ? $tenement_houses[0]['tenement_identify_tag_id'] : 0,
            'cmd' => $params['cmd'],
            'result' => Constant::EVENT_RESULT_MAP[$params['result']] ?? 0,
        ];
        $res = $this->device->post('/device/event/add', $post);
        if ($res['code'] !== 0) rsp_die_json(10001, '设备事件添加失败');

        //成功且事件属于轨迹事件时，转发到事件触发器
        if ($post['result'] == 1145 && in_array($params['cmd'], Constant::USER_TRAIL_TAG)) {
            $push_data = [
                'device_id' => $params['device_id'],
                'tenement_id' => $params['tenement_id'],
                'cmd' => $params['cmd'],
            ];
            $result = Comm_EventTrigger::push('user_trail', $push_data);
            if (empty($result)) {
                info(__METHOD__, ['error' => '事件触发器推送失败', 'push_data' => $push_data]);
            }
        }
        $tenement = $this->user->post('/tenement/lists', ['page' => 1, 'pagesize' => 1, 'tenement_id' => $params['tenement_id']]);
        $tenement = ($tenement['code'] === 0 && $tenement['content']) ? $tenement['content'][0] : [];
        $res = $this->user->post('/visitordevice/access', ['project_id' => $project_id, 'user_id' => $tenement['user_id'], 'device_id' => $params['device_id']]);
        info(__METHOD__, [$res]);
        if ($res['code'] !== 0) rsp_success_json(['event_id' => $event_id, 'valid' => false]);
        if ($res['content']['device_type'] === 'user') rsp_success_json(['event_id' => $event_id, 'valid' => true]);

        $result = ['event_id' => $event_id, 'valid' => !!$res['content']['valid_count']];
        info(__METHOD__, $result);
        rsp_success_json($result);
    }

    private function addVisitor($params = [])
    {
        $device = $this->device->post('/device/lists', ['page' => 1, 'pagesize' => 1, 'device_id' => $params['device_id']]);
        $device = ($device['code'] === 0 && $device['content']) ? $device['content'][0] : [];
        if (!$device) rsp_error_tips(10002, '设备');

        $abilities = array_unique(array_filter(array_column($device['device_ability_tag_ids'], 'device_ability_tag_id')));
        if (!in_array($params['cmd'], $abilities)) rsp_error_tips(10002, '设备能力');

        $device = $this->pm->post('/device/v2/lists', ['page' => 1, 'pagesize' => 1, 'device_id' => $params['device_id']]);
        $device = ($device['code'] === 0 && $device['content']) ? $device['content'][0] : [];
        if (!$device) rsp_error_tips(10002, '设备');
        $project_id = $device['project_id'];

        $event_id = resource_id_generator(Constant::RESOURCE_TYPES['device_event']);
        if (!$event_id) rsp_die_json(10001, '生成事件ID失败');

        $post = [
            'event_id' => $event_id,
            'device_id' => $params['device_id'],
            'visit_id' => $params['visit_id'],
            'cmd' => $params['cmd'],
            'result' => Constant::EVENT_RESULT_MAP[$params['result']] ?? 0,
        ];
        $res = $this->device->post('/device/event/add', $post);
        if ($res['code'] !== 0) rsp_die_json(10001, '设备事件添加失败');

        //成功时，转发到事件触发器
        if ($post['result'] == 1145 && in_array($params['cmd'], Constant::USER_TRAIL_TAG)) {
            $push_data = [
                'device_id' => $params['device_id'],
                'visit_id' => $params['visit_id'],
                'cmd' => $params['cmd'],
            ];
            $result = Comm_EventTrigger::push('user_trail', $push_data);
            if (empty($result)) {
                info(__METHOD__, ['error' => '事件触发器推送失败', 'push_data' => $push_data]);
            }
        }


        $visitor = $this->user->post('/visitor/lists', ['page' => 1, 'pagesize' => 1, 'visit_id' => $params['visit_id']]);
        $visitor = ($visitor['code'] === 0 && $visitor['content']) ? $visitor['content'][0] : [];
        $res = $this->user->post('/visitordevice/access', ['project_id' => $project_id, 'user_id' => $visitor['user_id'], 'device_id' => $params['device_id']]);
        info(__METHOD__, [$res]);
        if ($res['code'] !== 0) rsp_success_json(['event_id' => $event_id, 'valid' => false]);
        if ($res['content']['device_type'] === 'user') rsp_success_json(['event_id' => $event_id, 'valid' => true]);

        $result = ['event_id' => $event_id, 'valid' => !!$res['content']['valid_count']];
        info(__METHOD__, $result);
        rsp_success_json($result);
    }
}