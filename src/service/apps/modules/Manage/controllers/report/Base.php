<?php

include_once MANAGE_MODULE_PATH."/controllers/Common.php";

class Base
{
    use Common;

    protected $tag;
    protected $pm;
    protected $report;
    protected $addr;
    protected $project_id;
    
    public function __construct()
    {
        $this->project_id = $_SESSION['member_project_id'] ?? '';
        $this->tag = new Comm_Curl(['service' => 'tag', 'format' => 'json']);
        $this->report = new Comm_Curl(['service' => 'report', 'format' => 'json']);
        $this->pm = new Comm_Curl(['service' => 'pm', 'format' => 'json']);
        $this->addr = new Comm_Curl(['service' => 'addr', 'format' => 'json']);
    }
    
}