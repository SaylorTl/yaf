<?php
/**
 * 查询oauth2.0 租户关联第三方APPID信息
 */

namespace Project;

class ProjectModel
{

    public static function getAppProject($app_name)
    {
        $result = (new \Comm_Gateway())->gateway(
            ['name_en' => $app_name],
            'admin.appbinding.redisShow',
            ['service' => 'auth2']
        );
        log_message(__METHOD__ . '---xxxx--666----' . json_encode($result));
        if ($result['code'] != 0 || empty($result['content'])) {
            log_message('#####获取租户信息失败#####' . json_encode(['app_name' => $app_name, 'result' => $result], JSON_UNESCAPED_UNICODE));
            return false;
        }

        $params = [];
        $app_type = $result['content']['app_type'] ?? '';
        $app_id = $result['content']['jsfrom_source_id'] ?? '';
        $app_type == 'client' ? $params['client_app_id'] = $app_id : $params['admin_app_id'] = $app_id;

        $pm = new \Comm_Curl(['service' => 'pm', 'format' => 'json']);
        $lists = $pm->post('/project/projects', $params);

        if ($lists['code'] !== 0 || !$lists['content']) {
            log_message('#######查询项目信息失败|为空#######' . json_encode([$params, $lists], JSON_UNESCAPED_UNICODE));
            return false;
        }

        return array_column($lists['content'], 'project_id');
    }
}

