<?php

use Charging\ConstantModel;
use Car\UnlicensedModel;

final class Inout extends Base
{
    /**
     * 方向类型对应查的路由
     *  1350:预进场  1351：进场 1352:预出场 1353：出场
     */
    const INOUT_TYPES = [
        1350 => '/prein',
        1351 => '/inout',
        1352 => '/preout',
        1353 => '/inout'
    ];

    public function lists($params = [])
    {
        if (!isTrueKey($params, 'page', 'pagesize')) rsp_error_tips(10001, 'page、pagesize');

        $return = ['total' => 0, 'lists' => []];
        $params['project_id'] = $this->project_id ?: ($params['project_id'] ?? '');
        if ($params['project_id']) {
            unset($params['project_ids']);
        }
        if ($_SESSION['member_p_role_id'] == 0 && isset($params["project_ids"]) && $params["project_ids"] === 'all') {
            unset($params['project_id']);
        }
        if (isTrueKey($params, 'plate')) {
            $car_info = $this->car->post('/id', ['plate' => $params['plate']]);
            $car = $car_info['content'] ?? 0;
            unset($params['plate']);
            if (empty($car)) {
                rsp_success_json($return);
            }
            $params['car_id'] = $car;
        }

        $get_month = [];
        $check_date = ['recognition_time_begin', 'recognition_time_end', 'confirm_time_begin', 'confirm_time_end'];
        foreach ($check_date as $v) {
            if (isTrueKey($params, $v)) {
                $params[$v] = strtotime($params[$v]);
                $get_month[] = date("Ym", $params[$v]);
            }
        }
        $get_month = array_unique($get_month);
        if ($get_month && count($get_month) >= 2) {
            rsp_die_json(10001, '请选择相同月份的时间筛选');
        }
        $params['tabletime'] = $get_month[0] ?? date("Ym");

        // pm device_ids
        if (isTrueKey($params, 'space_id')) {
            $space_ids = $this->pm->post('/space/children', ['space_id' => $params['space_id']]);
            $space_ids = ($space_ids['code'] === 0 && $space_ids['content']) ? $space_ids['content'] : [];
            if (!$space_ids) rsp_success_json(['total' => 0, 'lists' => []]);
            unset($params['space_id']);
            $params['device_space_ids'] = array_unique(array_filter(array_column($space_ids, 'space_id')));
        }

        // device device_ids
        $where = [];
        if (is_not_empty($params, 'device_name')) $where['device_name'] = $params['device_name'];
        if (isTrueKey($params, 'device_type_tag_id')) $where['device_type_tag_id'] = $params['device_type_tag_id'];
        if ($where) {
            $device_device_ids = $this->device->post('/device/ids', $where);
            $device_device_ids = ($device_device_ids['code'] === 0 && $device_device_ids['content']) ? $device_device_ids['content'] : [];
            if (!$device_device_ids) rsp_success_json(['total' => 0, 'lists' => []]);
            $params['device_ids'] = array_unique(array_filter(array_column($device_device_ids, 'device_id')));
        }

        // lists
        $where = [
            'page' => $params['page'],
            'pagesize' => $params['pagesize']
        ];
        $fields = [
            'tabletime', 'car_id', 'inout_type_tag_id', 'business_type_tag_id', 'auto_tag_id', 'car_type_tag_id',
            'open_reason_tag_id', 'correct_car_id', 'recognition_time_begin', 'recognition_time_end',
            'confirm_time_begin', 'confirm_time_end', 'device_space_ids', 'direction_tag_id', 'project_id', 'project_ids'
        ];
        $where = array_merge($where, initParams($params, $fields));

        $path = $params['inout_type_tag_id'] ?? 1351;
        $path = self::INOUT_TYPES[$path] ?? '/inout';

        $count = $this->device->post("{$path}/count", $where);
        $count = $count['content'] ?? 0;
        if ($count <= 0) rsp_success_json($return);

        $lists = $this->device->post("{$path}/lists", $where);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json($return);

        // device info
        $device_ids = array_unique(array_filter(array_column($lists['content'], 'device_id')));
        $devices = $this->device->post('/device/devices', ['device_ids' => $device_ids]);
        $devices = ($devices['code'] === 0 && $devices['content']) ? many_array_column($devices['content'], 'device_id') : [];

        // project
        $projects = $this->pm->post('/project/projects', ['project_ids' => array_unique(array_filter(array_column($lists['content'], 'project_id')))]);
        $projects = ($projects['code'] === 0 && $projects['content']) ? many_array_column($projects['content'], 'project_id') : [];

        // car
        $car_ids = array_merge(array_column($lists['content'], 'car_id'), array_column($lists['content'], 'correct_car_id'));
        $cars = $this->car->post('/car/lists', ['ids' => array_unique(array_filter($car_ids))]);
        $cars = ($cars['code'] === 0 && $cars['content']) ? many_array_column($cars['content'], 'id') : [];

        $data = array_map(function ($m) use ($devices, $projects, $cars) {
            unset($m['temp_tenement_id']);
            $m['project_name'] = getArraysOfvalue($projects, $m['project_id'], 'project_name');
            $m['space_name'] = $m['device_space_name'];
            $m['device_name'] = getArraysOfvalue($devices, $m['device_id'], 'device_name');
            $m['device_type_tag_id'] = getArraysOfvalue($devices, $m['device_id'], 'device_type_tag_id');
            $m['plate'] = getArraysOfvalue($cars, $m['car_id'], 'plate');
            $m['car_brand_id'] = getArraysOfvalue($cars, $m['car_id'], 'brand_id');
            $m['car_series_id'] = getArraysOfvalue($cars, $m['car_id'], 'car_series_id');
            $m['plate_color_tag_id'] = getArraysOfvalue($cars, $m['car_id'], 'plate_color_tag_id');
            $m['car_color_tag_id'] = getArraysOfvalue($cars, $m['car_id'], 'car_color_tag_id');
            $m['correct_plate'] = getArraysOfvalue($cars, $m['correct_car_id'], 'plate');
            $m['recognition_time'] = $m['recognition_time'] ? date("Y-m-d H:i:s", $m['recognition_time']) : '';
            $m['confirm_time'] = $m['confirm_time'] ? date("Y-m-d H:i:s", $m['confirm_time']) : '';
            $m['total_amount'] = $m['total_amount'] / 100;
            $m['amount'] = $m['amount'] / 100;
            $m['coupon_amount'] = $m['coupon_amount'] / 100;
            $m['car_id'] = (string)$m['car_id'];
            $images = json_decode($m['images'], true);
            $m['images'] = is_array($images) ? $images : json_decode($images, true);
            return $m;
        }, $lists['content']);
        rsp_success_json(['total' => $count, 'lists' => $data]);
    }

    public function lists_detail($params = [])
    {
        if (!isTrueKey($params, 'project_id', 'car_id', 'business_type_tag_id')) {
            rsp_error_tips(10001, '请求信息不全');
        }
        // 获取车辆相关信息
        $contractCar = $this->contract->post('/contractdetail/lists', [
            'car_id' => $params['car_id'],
            'project_id' => $params['project_id'],
            'rule_type_tag_id' => $params['business_type_tag_id']
        ]);
        $car_info = $contractCar['content'][0] ?? [];
        if (empty($car_info)) {
            $car_info['real_name'] = $car_info['place_type_tag_id'] = $car_info['space_name_full'] = '';
        }
        if (isTrueKey($car_info, 'user_id')) {
            $user_Res = $this->user->post('/tenement/userlist', ['project_id' => $_SESSION['member_project_id'], 'user_ids' => [$car_info['user_id']]]);
            $user_content = array_column($user_Res['content'], null, 'user_id');
            $car_info['mobile'] = $user_content[$car_info['user_id']]['mobile'] ?? $car_info['mobile'];
            $car_info['real_name'] = ($user_content[$car_info['user_id']]['real_name'] ?? '') ?: $car_info['real_name'];
        }
        if (isTrueKey($car_info, 'place_id')) {
            $place_res = $this->pm->post('/parkplace/lists', ['place_ids' => $car_info['place_id']]);
            $place_info = $place_res['content'] ?? [];
            $content = array_column(($place_info ?: []), null, 'place_id');
            $car_info['place_type_tag_id'] = $content[$car_info['place_id']]['place_type'] ?? 0;
        }
        if (isTrueKey($car_info, 'house_id')) {
            if (!empty($house_content)) {
                $house_arr = $this->pm->post('/house/lists', ['house_ids' => $car_info['house_id']]);
                $house_res = array_column($house_arr['content'], null, 'house_id');
                $space_ids = array_unique(array_filter(array_column($house_arr['content'], 'space_id')));
                $space_branches = $this->pm->post('/space/batchBranch', ['space_ids' => $space_ids]);
                $space_branches = $space_branches['content'] ?? [];
                $branch_info = \Project\SpaceModel::parseBranch($space_branches[$car_info['space_id']] ?? []);
                $car_info['space_name_full'] = $branch_info['space_name_full'] ?? '';
                $car_info['space_id'] = isset($house_res[$car_info['house_id']]) ? $house_res[$car_info['house_id']]['space_id'] : $car_info['space_id'];
            }
        }

        info(__METHOD__, ['tip' => '查询车辆关联信息', 'data' => $params, 'result' => $contractCar]);
        rsp_success_json($car_info, '查询成功');
    }

    public function short_code($params = [])
    {
        if (!isTrueKey($params, 'short_code')) {
            rsp_die_json(10001, '缺少短码信息');
        }

        $redis = Comm_Redis::getInstance();
        $redis->select(0);

        if (isTrueKey($params, 'del_plate')) {
            $del = $redis->hdel(\Device\ConstantModel::SHORT_CODE, $params['short_code']);
            info(__METHOD__, ['tip' => '删除设备短码缓存车牌信息', 'data' => $params, 'result' => $del]);
        }

        $result = $redis->hget("PREIN:DEVICE_SHORT_CODE", $params['short_code']);
        $result = $result ? json_decode($result, true) : [];
        info(__METHOD__, ['tip' => '查询设备短码缓存车牌信息', 'data' => $params, 'result' => $result]);
        rsp_success_json($result['plate'] ?? '', '查询成功');
    }

    public function temp_zero_opening($params = [])
    {
        if (!isTrueKey($params, 'car_id', 'space_code', 'client_id', 'detail_uuid')) {
            rsp_die_json(10001, '请求信息不全');
        }

        // 校验计费信息是佛为0元以及车牌校验
        $redis = Comm_Redis::getInstance();
        $redis->select(8);
        $get = $redis->get(ConstantModel::ZERO_REDIS . "_{$params['detail_uuid']}");
        $get = $get ? json_decode($get, true) : [];
        if (empty($get) || !isTrueKey($get, 'car_id')) {
            rsp_die_json(10007, '计费信息已失效，请重新查询');
        }
        $amount = (int)bcmul(round(($get['pay_amount'] ?? -1), 2), 100);
        if ($amount) {
            rsp_die_json(10007, '开闸失败，计费金额不为0元');
        }

        // 校验空间短码是否正确
        $redis->select(0);
        $code_info = $redis->hget(\Device\ConstantModel::SHORT_CODE, $params['space_code']);
        $code_info = $code_info ? json_decode($code_info, true) : [];
        info(__METHOD__, ['tip' => '短码信息', 'result' => $code_info, 'data' => $params]);
        $plate = $code_info['plate'] ?? '';
        if (empty($code_info) || $plate != ($get['plate'] ?? '') || $params['car_id'] != $get['car_id']) {
            rsp_die_json(10007, '车辆不在道闸口，无法开闸');
        }

        // 获取空间ID
        $resource_lite = $this->resource->post('/resource/id/lite', [
            'resource_lite' => $params['space_code']
        ]);
        $space_id = $resource_lite['content'] ?? 0;
        if (!$space_id) {
            rsp_die_json(10007, '获取道闸信息失败，无法开闸');
        }
        $outer_id = $this->pm->post('/space/show', ['space_id' => $space_id]);
        $outer_id = $outer_id['content']['outer_id'] ?? 0;

        // 道闸远程控制下发
        $now = time();
        $sendRes = $this->device->post('/message/send', [
            'device_id' => $code_info['device_id'] ?? '',
            'cmd' => 1312,
            'data' => [
                'cmd' => 'open',
                'pid' => $outer_id
            ]
        ]);
        info(__METHOD__, ['tip' => '开闸下发结果', 'result' => $sendRes, 'data' => $params]);

        if ($sendRes['code'] != 0) {
            rsp_die_json(10007, "开闸失败【{$sendRes['message']}】");
        }

        // 删除道闸口缓存对应的车牌信息以及设置用户信息缓存用于出场纪录上报回填用户信息和异常开闸原因
        $del = $redis->hdel(\Device\ConstantModel::SHORT_CODE, $params['space_code']);
        info(__METHOD__, ['tip' => '删除设备短码缓存车牌信息', 'data' => $params, 'result' => $del]);

        $set = $redis->setex(
            \Device\ConstantModel::INOUT_CLIENT . "_{$get['through_id']}",
            3600,
            json_encode([
                'client_id' => $params['client_id'],
                'open_reason_tag_id' => 1630,
                'auto_tag_id' => 1345,
                'total_amount' => 0,
                'amount' => 0,
                'car_type_tag_id' => 1537,
                'business_type_tag_id' => 1510,
                'coupon_amount' => 0,
                'coupon_type' => '',
                'recognition_time' => $now,
                'confirm_time' => $now,
                'operator' => '其他',
                'remark' => '异常开闸'
            ])
        );
        info(__METHOD__, ['tip' => '缓存开闸用户信息', 'data' => $params, 'result' => $set]);

        rsp_success_json('', '开闸成功');
    }

    /**
     * @param array $params
     * 进场开闸
     */
    public function inOpen($params = [])
    {
        // 空间码
        if (!isTruekey($params, 'space_code')) {
            rsp_error_tips(10002, '空间信息错误');
        }
        // 业务类型区分 （eg:无牌车开闸）
        if (!isTruekey($params, 'business_type')) {
            rsp_error_tips(10002, '开闸业务类型错误');
        }

        // 用户信息
        $user_id = $_SESSION['user_id'];
        $client_id = $_SESSION['client_id'];

        // 通过空间码查询空间id
        $space_code = $params['space_code'];
        // todo 防止恶意点击开闸 ？？？
        $cache = Comm_Redis::getInstance()->hget("PREIN:DEVICE_SHORT_CODE", $space_code);
        $cache = $cache ? json_decode($cache, true) : [];
        if (!$cache) {
            rsp_die_json(10001, '道闸口无车辆');
        }

        $resource_id = $this->resource->post('/resource/id/lite', ['resource_lite' => $space_code]);
        $space_id = $resource_id['content'] ?? '';
        if (!$space_id) {
            rsp_die_json(10001, '空间码错误');
        }

        $business_type = $params['business_type'];
        // 事件触发器推送
        $message = [
            'project_id' => '', // 项目ID
            'space_id' => $space_id, // 道闸所属空间ID
            'user_id' => $user_id, // 用户ID
            'client_id' => $client_id, // 客户端用户ID
            'handel_type' => 'in', // 操作类型 in:进场、out:出场
            'business_type' => $business_type,
            'attach' => [],
        ];

        try {
            switch ($business_type) {
                case 'no_plate': // 无牌车
                    UnlicensedModel::open($message, ['space_id' => $space_id]);
                    break;
                case 'zero_open': // 0元开闸
                    // todo something
                    // ......
                    break;
                // todo other case
                // .......
                default:
                    rsp_die_json(10001, '业务类型错误');
            }
        } catch (Exception $e) {
            rsp_success_json('', $e->getMessage() ?? '系统内部错误');
        }

        $result = Comm_EventTrigger::push('inout_device_opening_event', $message);
        if (!$result || empty($result) || $result['code'] != 0) {
            info(__METHOD__, ['error' => '无牌车扫码入场消息推送失败', $message]);
            rsp_success_json('', '开闸失败');
        }
        rsp_success_json('', '开闸成功');
    }

}