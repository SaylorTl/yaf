<?php
/*
请求参数:
{
    "project_id": "111111",
    "tenement_id": "222222"
}

响应参数:
{
    "code": "success",
    "content": {
        "total": 2,
        "data": [
            {
                "id": "10000000000000",
                "name": "A单元入口",
                "mac": "0081f9b13c28",
                "key": "C335668DF7F1ADD33D8EEC043832D452EB21E0BCF9209457709ED718FA4B6B6D"
            },
            {
                "id": "10000000000001",
                "name": "B单元入口",
                "mac": "0081f9b13c28",
                "key": "C335668DF7F1ADD33D8EEC043832D452EB21E0BCF9209457709ED718FA4B6B6D"
            }
        ]
    }
}
*/

class Lock extends Base
{
    public function keys($params)
    {
//        log_message(__METHOD__ . '------' . json_encode($params));
//        if (!isTrueKey($params, 'project_id', 'tenement_id')) {
//            rsp_die_json(10001, 'project_id，tenement_id 参数缺失或错误');
//        }
//
//        $project_id = $params['project_id'];
//        $tenement_id = $params['tenement_id'];
//
//        $project =  $this->pm->post('/project/lists', ['project_id' => $project_id]);
//        $project = ($project['code'] === 0 && $project['content']) ? $project['content'][0] : [];
//        $project_device_templates = array_column($project['project_device_templates'], 'device_template_id');
//
//        $devices = [];
//        if ($project_device_templates) {
//            $devices = $this->device->post('/device/ids', ['project_id' => $project_id, 'device_template_ids' => $project_device_templates]);
//            $devices = ($devices['code'] === 0 && $devices['content']) ? $devices['content'] : [];
//        }
//        // 开发设备则不做校验
//        if ($devices) {
//            $device_ids = $this->pm->post('/device/v2/lists', ['project_id' => $project_id, 'device_ids' => array_unique(array_filter(array_column($devices, 'device_id')))]);
//            $device_ids = ($device_ids['code'] === 0 && $device_ids['content']) ? $device_ids['content'] : [];
//        } else {
//            // 认证状态校验
//            $tenements = $this->user->post('/tenement/userlist', ['page' => 1, 'pagesize' => 1, 'tenement_ids' => [$tenement_id], 'tenement_check_status' => 'Y']);
//            $tenements = ($tenements['code'] === 0 && $tenements['content']) ? $tenements['content']['lists'] : [];
//            if (!$tenements) {
//                rsp_die_json(10001, '您的蓝牙已过有效期，请联系管理员。');
//            }
//
//            $houses = $this->user->post('/house/lists', ['tenement_ids' => [$tenement_id], 'tenement_house_status' => 'Y']);
//            $houses = ($houses['code'] === 0 && $houses['content']) ? $houses['content'] : [];
//
//            // 没有已认证的房子就查大门
//            if (!$houses) {
//                $device_ids = $this->pm->post('/device/v2/lists', ['project_id' => $project_id, 'not_house_frame_id' => 1]);
//                $device_ids = ($device_ids['code'] === 0 && $device_ids['content']) ? $device_ids['content'] : [];
//            } else {
//                $houses = $this->pm->post('/house/basic/lists', ['project_id' => $project_id, 'house_ids' => array_unique(array_filter(array_column($houses, 'house_id')))]);
//                $houses = ($houses['code'] === 0 && $houses['content']) ? $houses['content'] : [];
//                if ($houses) {
//                    // 查询项目管理的设备id
//                    $house_ids = array_unique(array_filter(array_column($houses, 'house_id')));
//                    $device_ids = $this->pm->post('/device/v2/ids', ['house_ids' => $house_ids]);
//                    $device_ids = ($device_ids['code'] === 0 && $device_ids['content']) ? $device_ids['content'] : [];
//                } else {
//                    $device_ids = [];
//                }
//            }
//        }
//
//        if (!$device_ids) {
//            rsp_die_json(10001, '您的蓝牙已过有效期，请联系管理员。');
//        }
//
//        // 查询设备信息
//        $tmp = $this->device->post('/device/lists', ['device_ids' => array_unique(array_filter(array_column($device_ids, 'device_id')))]);
//        if ($tmp['code'] != 0 || empty($tmp['content'])) {
//            rsp_success_json(['total' => 0, 'lists' => []]);
//        }
//
//        $items = [];
//        array_walk($tmp['content'], function ($rows, $key) use (&$items) {
//            if (!$rows['device_vendor_detail'] || !json_decode($rows['device_vendor_detail'])) {
//                return;
//            }
//            $detail = json_decode($rows['device_vendor_detail'], true);
//            $device_mac = isset($detail['mac']) ? str_replace(':', '', strtoupper($detail['mac'])) : '';
//
//            $items[] = [
//                'id' => $rows['device_id'],
//                'abilities' => array_column($rows['device_ability_tag_ids'], 'device_ability_tag_id', null),
//                'name' => $rows['device_name'],
//                'mac' => $device_mac,
//                'key' => $detail['key'] ?? '',
//            ];
//        });
//
//        $result = [
//            'total' => count($items),
//            'lists' => $items ?: []
//        ];
//
//        rsp_success_json($result);
        rsp_success_json([]);
    }
}