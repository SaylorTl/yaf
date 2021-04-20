<?php
/**
 * 欠费列表对接计费系统
 */

namespace Project;

class ArrearsModel
{

    public static function handle($params = [])
    {
        if (isTrueKey($params, 'space_id') === false) return;
        $cost = new \Comm_Curl(['service' => 'billing', 'format' => 'json']);
        $pm = new \Comm_Curl(['service' => 'pm', 'format' => 'json']);

        $result = $cost->post('/receivableBill/lists', [
            'space_id' => $params['space_id'],
            'billing_status_tag_id' => 1506
        ]);
        if (!$result || 0 != (int)$result['code']) {
            log_message(__METHOD__ . '---账单信息查询异常：' . json_encode([$result]) . '--空间id:' . $params['space_id']);
            return;
        }
        if (empty($result['content'])) {
            $result = $pm->post('/arrears/updateV2', [
                'space_id' => $params['space_id']
            ]);
            log_message(__METHOD__ . '---修改欠费记录结果：' . json_encode([$result]) . '--空间id:' . $params['space_id']);
            return;
        }
        $bills = $result['content'];
        //整合参数  更新欠费数据
        $result = $pm->post('/arrears/show', [
            'space_id' => $params['space_id'],
            'arrears_month' => date('Y.m')
        ]);
        if (empty($result['content'])) {
            log_message(__METHOD__ . '----没有欠费记录:' . json_encode([$result]) . '----空间id:' . $params['space_id']);
            return;
        }

        self::fillParams($bills, $result['content']);
        $result = $pm->post('/arrears/update', $result['content']);
        if (0 !== (int)$result['code']) {
            log_message(__METHOD__ . '----修改欠费记录失败:' . json_encode([$result]) . '----空间id:' . $params['space_id']);
        }
    }

    public static function fillParams($bills, &$data)
    {
        $sub_details = $dates = [];
        $total_amount = $penalty_amount = 0;
        foreach ($bills as $v) {
            $tmp = [
                'arrears_detail' => $v['billing_account_name'],
                'arrears_sub_amount' => bcmul($v['billing_amount'], 100),
                'arrears_sub_penalty_money' => bcmul($v['billing_penalty_amount'], 100),
                'arrears_sub_month' => date('Y-m', strtotime($v['create_time']))
            ];
            $total_amount += $tmp['arrears_sub_amount'];
            $penalty_amount += $tmp['arrears_sub_penalty_money'];
            array_push($dates, $tmp['arrears_sub_month']);
            $sub_details[] = $tmp;
        }
        $dates = array_unique($dates);
        sort($dates);
        $count = count($dates);
        $data['arrears_begin_time'] = strtotime($dates[0]);
        $data['arrears_end_time'] = strtotime($dates[$count - 1]);
        $data['total_amount'] = $total_amount;
        $data['penalty_money'] = $penalty_amount;
        $data['sub_arrears_detail'] = json_encode($sub_details);
    }
}