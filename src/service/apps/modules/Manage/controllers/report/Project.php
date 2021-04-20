<?php


class Project extends Base
{
    public function basicInfo($params = [])
    {
        if (!$this->project_id) {
            rsp_error_tips(10001, '项目信息');
        }
        //查询统计数据
        $result = [
            'project_id' => $this->project_id,
            'project_name' => '',
            'project_address' => '',
        ];
        $res = $this->report->post('/projectBasicInfo/show', ['project_id' => $this->project_id]);
        $result = ($res['code'] == 0 && $res['content'] && is_array($res['content']))
            ? array_merge($result, $res['content'])
            : [];
        //查询项目信息
        $res = $this->pm->post('/project/show', ['project_id' => $this->project_id]);
        if ($res['code'] == 0 && $res['content']) {
            $result['project_name'] = $res['content']['project_name'] ?? '';
            $addr = '';
            //国家
            if (!empty($res['content']['country_id'])) {
                $tag_info = $this->tag->post('/tag/show', ['tag_id' => $res['content']['country_id']]);
                $addr .= $tag_info['code'] == 0 ? $tag_info['content']['tag_name'] : '';
            }
            //省市区
            $addr .= $this->getAddrName($res['content']['province_id'] ?? '');
            $addr .= $this->getAddrName($res['content']['city_id'] ?? '');
            $addr .= $this->getAddrName($res['content']['region_id'] ?? '');
            $addr .= $res['content']['address_detail'] ?? '';
            $result['project_address'] = $addr;
        }
        rsp_success_json($result);
    }
    
    
    private function getAddrName($code)
    {
        if (!$code) {
            info('----BaseData/'.__FUNCTION__.'----', ['error' => 'code参数缺失']);
            return '';
        }
        $data = $this->addr->post('/addrcode/show', ['code' => $code]);
        return $data['content']['data']['name'] ?? '';
    }
}