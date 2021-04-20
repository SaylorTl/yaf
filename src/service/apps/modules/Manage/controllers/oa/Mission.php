<?php

class Mission extends Base
{

    public function info()
    {
        $cfg = getConfig('other.ini');

        if ($this->p_role_id != 0) {
            $wos_system_source_id = $cfg->get('wos_system_source_id');
            if (is_null($wos_system_source_id)) {
                rsp_success_json(['lists' => []], 'wos_system_source_id 配置项缺失');
            }
            $subsystem_source_ids = $this->getSubsystemSourceIds();
            if (!in_array($wos_system_source_id, $subsystem_source_ids)) {
                rsp_success_json(['lists' => []], '功能不可用，没有工单管理系统权限');
            }
        }

        $params = [
            "page" => 1,
            "pageSize" => 5,
            "status" => [598],//执行中
            "c" => "performer",
            "m" => "personal",
            "project_ids" => "all"
        ];

        // 当前操作人（登录用户）
        $params['operator'] = $this->employee_id ?? '';
        // 当前操作人（登录用户所属组织）
        $params['frame'] = $this->frame_id ?? '';

        $result = $this->wos->get('/list', $params);
        log_message('---mission/info----查询工单信息---' . json_encode([$params, $result]));

        if ($result['code'] != 0 || empty($result['content']['rows'])) {
            rsp_success_json(['lists' => []], 'success');
        }

        $data = [];
        $host = $cfg->get('gcenter.host') ?: '';
        foreach ($result['content']['rows'] as $key => $items) {
            $data[$key]['date'] = date('m/d',strtotime($items['create_at']));
            $data[$key]['title'] = $items['title'];
            $data[$key]['host'] = $host;
        }

        rsp_success_json(['lists' => $data]);
    }

}