<?php

use Wos\TidingModel as Tiding;

class Drama extends Base
{
    public function __construct()
    {
        parent::__construct();
        //工单系统权限判断
        if ($this->p_role_id != 0) {
            $cfg = getConfig('other.ini');
            $wos_system_source_id = $cfg->get('wos_system_source_id');
            if (is_null($wos_system_source_id)) {
                rsp_die_json(90002, 'wos_system_source_id 配置项缺失');
            }
            $subsystem_source_ids = $this->getSubsystemSourceIds();
            if (!in_array($wos_system_source_id, $subsystem_source_ids)) {
                rsp_die_json(90002, '功能不可用，没有工单管理系统权限');
            }
        }
    }
    
    /**
     * @param $input
     * 创建工单
     */
    public function create($input)
    {
        $params = $this->parameters([
            'kind' => [self::T_JSON, true], // 分类
            'multipart' => [self::T_JSON, true], // 动态表单数据
            'audience' => [self::T_JSON], // 关注人「可见人」
            'attachements' => [self::T_JSON,], // 附件
            'performer' => [self::T_JSON, true], // 执行人
            'references' => [self::T_JSON],
            'sid' => [self::T_STRING],
            'visibility' => [self::T_STRING], // 可见范围
            'source' => [self::T_INT, true], // 工单来源
        ], $input);
        if (isset($params['multipart']['content']) && mb_strlen($params['multipart']['content']) > 1000) {
            rsp_die_json(10001, '描述内容字符数不能超过1000');
        }
        $params['sid'] = $params['sid'] ?? resource_id_generator(self::RESOURCE_TYPES['wos']);
        $params['from_id'] = $this->from_id;
        $params['operator'] = $params['operator'] ?? $this->employee_id;
        $params['visibility'] = $params['visibility'] ?? 'private'; // 默认私有
        log_message('----test----create'.json_encode($params, JSON_UNESCAPED_UNICODE));
        $result = $this->wos->post('/create', $params);
        if ($result['code'] != 0) {
            rsp_die_json(10005, '工单创建失败', '', $result['message']);
        }
        rsp_success_json($result['content'] ?? []);
    }
    
    /**
     * @param $input
     * 发布工单
     */
    public function publish($input)
    {
        $params = $this->parameters([
            '_id' => [self::T_STRING, true],
            '__v' => [self::T_INT, true],
        ], $input);
        $params['frame'] = $params['frame'] ?? $this->frame_id;
        $params['operator'] = $params['operator'] ?? $this->employee_id;
        log_message('----test----publish'.json_encode($params, JSON_UNESCAPED_UNICODE));
        $result = $this->wos->get('/detail', $params);
        if ($result['code'] != 0) {
            rsp_die_json(10002, '发布失败，工单详情查询异常，'.$result['message']);
        }
        $this->wos->post('/publish', $params);
        //发送消息
        $result['content']['tiding_type'] = 'change_order';
        Tiding::push($result['content']);
        rsp_success_json('已发布，结果以实际情况为准');
    }
    
    /***
     * 获取当前登录用户的子系统来源ID
     * @return array
     */
    private function getSubsystemSourceIds()
    {
        $user_params = [
            'employee_id' => $this->employee_id,
        ];
        $subsystem_info = $this->access->post('/user/lists', $user_params);
        if ($subsystem_info['code'] != 0) {
            rsp_die_json(10002, '当前登录用户的子系统信息查询失败，'.$subsystem_info['message']);
        }
        $subsystem_source_ids = [];
        if ($subsystem_info['content']) {
            $subsystem_source_ids = array_column($subsystem_info['content'], 'source_id');
        }
        return $subsystem_source_ids;
    }
    
}