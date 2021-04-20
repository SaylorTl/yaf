<?php

class PersonnelInout extends Base
{
    
    public function perHour($params = [])
    {
        if (!$this->project_id) {
            rsp_error_tips(10001, '项目信息');
        }
        $res = $this->report->post('/personnelInout/perHourReport', ['project_id' => $this->project_id] + $params);
        if (!isset($res['code']) || $res['code'] != 0) {
            $msg = isset($res['message']) ? '，'.$res['message'] : '';
            rsp_die_json(10002, '查询失败'.$msg);
        }
        rsp_success_json($res['content']);
    }
    
    public function overview($params = [])
    {
        if (!$this->project_id) {
            rsp_error_tips(10001, '项目信息');
        }
        $res = $this->report->post('/personnelInout/overviewReport', ['project_id' => $this->project_id] + $params);
        if (!isset($res['code']) || $res['code'] != 0) {
            $msg = isset($res['message']) ? '，'.$res['message'] : '';
            rsp_die_json(10002, '查询失败'.$msg);
        }
        $tag_info = $this->getTagInfo();
        $res['content'] = array_map(function ($m) use ($tag_info) {
            $m['details'] = $m['details'] ?? [];
            //没有身份的默认为 “访客”
            $m['details']['1133'] = $m['details']['1133'] ?? 0;
            $m['details']['0'] = $m['details']['0'] ?? 0;
            $m['details']['1133'] += $m['details']['0'];
            unset($m['details']['0']);
            $details = [];
            foreach ($m['details'] as $k => $v) {
                $details[] = [
                    'identify_name' => getArraysOfvalue($tag_info, $k, 'tag_name'),
                    'num' => $v
                ];
            }
            $m['details'] = $details;
            return $m;
        }, $res['content']);
        rsp_success_json($res['content']);
    }
    
    private function getTagInfo()
    {
        //标签信息
        $tag_info = $this->tag->post('/tag/lists', ['type_id' => 127]);
        $tag_info = $tag_info['code'] == 0 ? many_array_column($tag_info['content'], 'tag_id') : [];
        if (empty($tag_info)) {
            rsp_die_json(90002, '【住户身份】标签信息查询失败或缺失');
        }
        return $tag_info;
    }
}