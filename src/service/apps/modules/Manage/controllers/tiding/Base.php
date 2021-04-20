<?php

class Base
{
    const RESOURCE_TYPES = [
        'tiding' => 10026,
    ];

    const AUDIENCE_TYPE = [
        'employee' => 's', //员工
        'frame' => 'e' //组织
    ];

    protected $tiding;
    protected $user;
    protected $employee_id;
    protected $service_source = [
        'RMS' => ['subapp' => ''],
        'RMS_AUTH' => ['subapp' => ''],
        'WOMS' => ['subapp' => ''],
    ];

    protected $kind = [
        'rob_order' => '抢单通知',
        'change_order' => '工单变更',
        'act_notify' => '活动通知',
        'finish_order' => '完成工单',
    ];

    public function __construct()
    {
        $this->tiding = new Comm_Curl(['service' => 'tiding', 'format' => 'json']);
        $this->user = new Comm_Curl(['service' => 'user', 'format' => 'json']);
        $this->employee_id = $_SESSION['employee_id'] ?? '';
        $config = Yaf_Application::app()->getConfig();
        foreach ($this->service_source as $k => &$v) {
            $v['subapp'] = $config->get($k . '.SUBAPP');
        }
    }

    public function getScope($params, $path)
    {
        $scope = (new Comm_Gateway())->gateway($params, 'admin.scope.' . $path, ['service' => 'auth2']);
        return $scope;
    }

}

