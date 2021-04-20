<?php

class Unlicensed extends Base
{

    /**
     * @param array $params
     * 无牌车扫码进场
     */
    public function parking($params = [])
    {
        //todo 发送开闸指令后 入库无牌车到场记录
        $data = [];
        if (!isTruekey($params, 'space_code')) {
            rsp_error_tips(10002, '空间码缺失');
        }
        // 通过空间码查询空间id
        $space_code = $params['space_code'];
        $cache = Comm_Redis::getInstance()->hget("PREIN:DEVICE_SHORT_CODE", $space_code);
        $cache = $cache ? json_decode($cache, true) : [];
        if (!$cache) {
            rsp_die_json(10001, '道闸口无车辆');
        }
        if ($cache['plate'] != '无牌车') {
            rsp_die_json(10001, '不是无牌车');
        }
        $resource_id = $this->resource->post('/resource/id/lite', ['resource_lite' => $space_code]);
        $space_id = $resource_id['content'] ?? '';
        if (!$space_id) {
            rsp_die_json(10001, '空间码错误');
        }

        // 通过空间id获取空间信息
        $space = $this->pm->post('/space/show', ['space_id' => $space_id]);
        if (!$space || empty($space['content'])) {
            rsp_die_json(10001, '空间信息不存在');
        }

        // 根据项目id查询项目信息
        $project_id = $space['content']['project_id'];
        $project = $this->pm->post('/project/show', ['project_id' => $project_id]);
        if (!$project || empty($project['content'])) {
            rsp_die_json(10001, '项目信息不存在');
        }

        // 查询月卡信息
        $is_contract = false; // 是否有月卡
        $user_id = $_SESSION['user_id'];
        $contract = $this->contract->post('/contract/joinList', ['project_id' => $project_id, 'user_id' => $user_id]);
        if ($contract && !empty($project['content']) && count($project['content']) > 0) {
            $is_contract = true;
        }

        // 查询是否有未出场的无牌车记录
        $unlicensed = $this->car->post('/unlicensed/lists', ['project_id' => $project_id, 'user_id' => $user_id]);
        if (!$unlicensed) {
            info('/unlicensed/lists', ['请求超时']);
            rsp_die_json(10001, '请求超时');
        }
        $is_parking = false; // 是否在场
        if ($unlicensed['code'] == 0 && !empty($unlicensed['content'])) { // 在场
            $is_parking = true;
        }

        $data['project_id'] = $project['content']['project_id'] ?? ''; // 项目id
        $data['project_name'] = $project['content']['project_name'] ?? ''; // 项目名
        $data['address_detail'] = $project['content']['address_detail'] ?? ''; // 项目地址
        $data['space_id'] = $space_id; // 空间i
        $data['space_code'] = $space_code; // 空间id
        $data['direction'] = $space['content']['direction'] ?? 0;
        $data['space_name'] = $space['content']['space_name'] ?? ''; // 空间名
        $data['is_contract'] = $is_contract; // 是否月卡用户
        $data['is_parking'] = $is_parking; // 是否在场

        rsp_success_json($data, 'success');
    }


    /**
     * @param array $params
     * 进场查询用户是否存在在场无牌车记录
     */
    public function lists($params = [])
    {
        $match = [
            'through_id' => $params['through_id'],
            'user_id' => $params['user_id'],
            'client_id' => $params['client_id'],
            'project_id' => $params['project_id'],
            'space_id' => $params['space_id'],
            'mobile' => $params['mobile'],
        ];
        $result = $this->car->get('/unlicensed/lists', $match);
        if (!$result || $result['code'] !== 0) {
            rsp_die_json(10005, $result['message'] ?? '请求超时');
        }
        rsp_success_json($result, 'success');
    }

}