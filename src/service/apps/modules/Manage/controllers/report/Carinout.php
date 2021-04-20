<?php

class CarInout extends Base
{
    
    public function perHour($params = [])
    {
        if (!$this->project_id) {
            rsp_error_tips(10001, '项目信息');
        }
        $res = $this->report->post('/carInout/perHourReport', ['project_id' => $this->project_id] + $params);
        if (!isset($res['code']) || $res['code'] != 0) {
            $msg = isset($res['message']) ? '，'.$res['message'] : '';
            rsp_die_json(10002, '查询失败'.$msg);
        }
        $tag_info = $this->getTagInfo(167);
        if ($tag_info) {
            $res['content'] = array_map(function ($m) use ($tag_info) {
                $temp = [];
                foreach ($m as $k => $v) {
                    $type_name = getArraysOfvalue($tag_info, $k, 'tag_name');
                    $type_name = $type_name ?: '未知-'.$k;
                    $temp[$type_name] = $v;
                }
                return $temp;
            }, $res['content']);
        }
        rsp_success_json($res['content']);
    }
    
    public function overview($params = [])
    {
        if (!$this->project_id) {
            rsp_error_tips(10001, '项目信息');
        }
        $res = $this->report->post('/carInout/overviewReport', ['project_id' => $this->project_id] + $params);
        if (!isset($res['code']) || $res['code'] != 0) {
            $msg = isset($res['message']) ? '，'.$res['message'] : '';
            rsp_die_json(10002, '查询失败'.$msg);
        }
        $type_tag_info = $this->getTagInfo(167);
        $business_tag_info = $this->getTagInfo(187);
        $res['content'] = array_map(function ($m) use ($type_tag_info, $business_tag_info) {
            $group_by_business = [['business_name' => '其他', 'num' => 0]];
            foreach ($m['details'] as $type => $value) {
                foreach ($value as $business => $num) {
                    $business_name = $business ? getArraysOfvalue($business_tag_info, $business, 'tag_name') : '其他';
                    $business_name = $business_name ?: '未知-'.$business_name;
                    if (isset($group_by_business[$business])) {
                        $group_by_business[$business]['num'] += $num;
                    } else {
                        $group_by_business[$business] = [
                            'business_name' => $business_name,
                            'num' => $num
                        ];
                    }
                }
            }
            $m['group_by_business'] = array_values($group_by_business);
            unset($details);
            unset($m['details']);
            $group_by_type = [];
            foreach ($m['total'] as $type => $num) {
                $type_name = getArraysOfvalue($type_tag_info, $type, 'tag_name');
                $type_name = $type_name ?: '未知-'.$type;
                $group_by_type[] = [
                    'type_name' => $type_name,
                    'num' => $num
                ];
            }
            $m['group_by_type'] = $group_by_type;
            unset($m['total']);
            return $m;
        }, $res['content']);
        rsp_success_json($res['content']);
    }
    
    private function getTagInfo(int $type_id)
    {
        //标签信息
        $tag_info = $this->tag->post('/tag/lists', ['type_id' => $type_id]);
        $tag_info = $tag_info['code'] == 0 ? many_array_column($tag_info['content'], 'tag_id') : [];
        if (empty($tag_info)) {
            rsp_die_json(90002, '【住户身份】标签信息查询失败或缺失');
        }
        return $tag_info;
    }
}