<?php


class BusinessOrder extends Base
{
    const API_MAP = [
        'daily' => '/businessOrder/dailyReport',
        'monthly' => '/businessOrder/monthlyReport',
        'annual' => '/businessOrder/annualReport',
    ];
    
    public function overview($params = [])
    {
        if (!$this->project_id) {
            rsp_error_tips(10001, '项目信息');
        }
        $result = [];
        //标签信息
        $tag_info = $this->getTagInfo();
        //今日收入
        $res = $this->report->post('/businessOrder/dailyReport', ['project_id' => $this->project_id]);
        $result['that_day'] = $res['code'] == 0 ? $res['content'][date('Y-m-d')] : [];
        $result['that_day'] = $this->changeData($result['that_day'], $tag_info);
        //当月收入
        $res = $this->report->post('/businessOrder/monthlyReport', ['project_id' => $this->project_id]);
        $result['this_month'] = $res['code'] == 0 ? $res['content'][date('Y-m')] : [];
        $result['this_month'] = $this->changeData($result['this_month'], $tag_info);
        //年度收入
        $res = $this->report->post('/businessOrder/annualReport', ['project_id' => $this->project_id]);
        $result['this_year_total'] = $res['code'] == 0 ? array_sum($res['content'][date('Y')]) / 100 : 0;
        rsp_success_json($result);
    }
    
    private function getTagInfo()
    {
        //标签信息
        $tag_info = $this->tag->post('/tag/lists', ['type_id' => 121]);
        $tag_info = $tag_info['code'] == 0 ? many_array_column($tag_info['content'], 'tag_id') : [];
        if (empty($tag_info)) {
            rsp_die_json(90002, '【费用类型】标签信息查询失败或缺失');
        }
        return $tag_info;
    }
    
    private function changeData($data, $tag_info)
    {
        $res = [];
        $temp_amount = 0;
        foreach ($data as $key => $value) {
            if (!in_array($key, [697, '698'])) {
                if (isset($res['其他'])) {
                    $temp_amount += ($value / 100);
                } else {
                    $temp_amount = $value / 100;
                }
                $res['其他'] = [
                    'trade_source_name' => '其他',
                    'amount' => $temp_amount,
                ];
            } else {
                $trade_source_name = getArraysOfvalue($tag_info, $key, 'tag_name');
                $res[$trade_source_name] = [
                    'trade_source_name' => $trade_source_name,
                    'amount' => $value / 100,
                ];
            }
        }
        return array_values($res);
    }
    
    public function report($params = [])
    {
        if (!isTrueKey($params, 'begin', 'end', 'date_type')) {
            rsp_error_tips(10001, 'begin 、end 或 date_type');
        }
        if (!isset(self::API_MAP[$params['date_type']])) {
            rsp_die_json(10001, 'date_type 参数错误，取值范围：'.implode('、', array_keys(self::API_MAP)));
        }
        if (!$this->project_id) {
            rsp_error_tips(10001, '项目信息');
        }
        //标签信息
        $tag_info = $tag_info = $this->getTagInfo();
        //今日收入
        $res = $this->report->post(self::API_MAP[$params['date_type']], [
            'project_id' => $this->project_id,
            'begin' => $params['begin'],
            'end' => $params['end'],
        ]);
        if (!isset($res['code']) || $res['code'] != 0) {
            $msg = isset($res['message']) ? '，'.$res['message'] : '';
            rsp_die_json(10002, '查询失败'.$msg);
        }
        $result = [];
        foreach ($res['content'] as $key => $value) {
            $result[$key] = $this->changeData($value, $tag_info);
        }
        rsp_success_json($result);
    }
}