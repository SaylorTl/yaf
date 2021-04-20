<?php

class Base
{
    const RESOURCE_TYPES = [
        'device'               => 10009,
        'device_template'      => 10031,
        'vendor'               => 10032,
    ];

    const TENEMENT_STATUS = [
        '使用中' => 927,
        '已搬离' => 928,
    ];

    protected $device;

    protected $user;

    protected $pm;

    protected $resource;

    protected $project_id;

    protected $employee_id;

    public function __construct(){
        $this->device = new Comm_Curl([ 'service'=>'device','format'=>'json']);
        $this->user = new Comm_Curl([ 'service'=>'user','format'=>'json']);
        $this->pm = new Comm_Curl([ 'service'=>'pm','format'=>'json']);
        $this->resource = new Comm_Curl([ 'service'=>'resource','format'=>'json']);
        $this->project_id = $_SESSION['member_project_id'] ?? '';
        $this->employee_id = $_SESSION['employee_id'] ?? '';
    }

}

