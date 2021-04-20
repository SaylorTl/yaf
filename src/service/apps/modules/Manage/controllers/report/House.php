<?php


class House extends Base
{
    public function housingStatus($params = [])
    {
        if (!$this->project_id) {
            rsp_error_tips(10001, '项目信息');
        }
        //查询统计数据
        $res = $this->report->post('/housingStatus/show', ['project_id' => $this->project_id]);
        $result = ($res['code'] == 0 && $res['content']) ? $res['content'] : [];
        if ($result) {
            $result['houses_total_grouped_by_use'] = $this->addTagName(44,
                $result['houses_total_grouped_by_use'] ?? [], 'use_name');
            $result['houses_total_grouped_by_type'] = $this->addTagName(45,
                $result['houses_total_grouped_by_type'] ?? [], 'type_name');
        }
        rsp_success_json($result);
    }
    
    private function addTagName(int $type_id, array $data, string $key_name)
    {
        //标签信息
        $tag_info = $this->tag->post('/tag/lists', ['type_id' => $type_id]);
        $tag_info = $tag_info['code'] == 0 ? many_array_column($tag_info['content'], 'tag_id') : [];
        $res = [];
        foreach ($tag_info as $v) {
            $res[] = [
                $key_name => $v['tag_name'],
                'total' => $data[$v['tag_id']] ?? 0,
            ];
        }
        return $res;
    }
}