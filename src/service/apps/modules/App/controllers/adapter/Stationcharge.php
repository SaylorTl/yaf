<?php

class Stationcharge extends Base
{

    public function contractFee($params = [])
    {
        log_message(__METHOD__ . '---【计费】---' . json_encode($params, JSON_UNESCAPED_UNICODE));
        $params['mobile'] = $params['mobile'] ?? $_SESSION['user_mobile'];
        if (!isTrueKey($params, 'contract_id', 'mobile', 'month_total')) {
            rsp_die_json(10001, '请求信息不全');
        }

        $fee = $this->station_adapter->post('/ep/contract/fee', $params);
        if ($fee['code'] != 0) {
            rsp_die_json(10007, $fee['message']);
        }
        rsp_success_json($fee['content'], '请求成功');
    }

    public function contractLists($params = [])
    {
        log_message(__METHOD__ . '---【月卡列表】---' . json_encode($params, JSON_UNESCAPED_UNICODE));
        $params['mobile'] = $params['mobile'] ?? $_SESSION['user_mobile'];
        if (!isTrueKey($params, 'project_id', 'mobile')) {
            rsp_die_json(10001, '请求信息不全');
        }

        $lists = $this->station_adapter->post('/ep/contract/lists', $params);
        if ($lists['code'] != 0) {
            rsp_die_json(10007, $lists['message']);
        }
        rsp_success_json($lists['content'], '请求成功');
    }
}


