<?php

class Base
{
    
    const RESOURCE_TYPES = [
        'wos' => 10027,
    ];

    const SEARCH_TYPES = [
        'name',
        'mobile',
        'plate',
        'tnum',
        'project'
    ];

    const LIST_KEY = 'oa_search_params';

    protected $wos;
    protected $access;
    protected $tag;
    protected $employee_id;
    protected $frame_id;
    protected $from_id;
    protected $p_role_id;

    public function __construct()
    {
        $this->employee_id = $_SESSION['employee_id'] ?? '0';
        $this->frame_id = $_SESSION['employee_frame_id'] ?? '0';
        $this->from_id = $_SESSION['member_jsfrom_id'] ?? '0';
        $this->p_role_id = $_SESSION['member_p_role_id'] ?? -888888;
        $this->wos = new Comm_Curl(['service' => 'wos', 'format' => 'json']);
        $this->access = new Comm_Curl(['service' => 'access', 'format' => 'json']);
    }

    /***
     * 获取当前登录用户的子系统来源ID
     * @return array
     */
    public function getSubsystemSourceIds()
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