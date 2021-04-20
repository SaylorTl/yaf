<?php

include_once MANAGE_MODULE_PATH."/controllers/Common.php";

class Base
{
    use Common;
    
    /**
     * parameters filter
     */
    const T_RAW = 0x1;
    const T_INT = 0x2;
    const T_URL = 0x8;
    const T_JSON = 0x10;
    const T_BOOL = 0x20;
    const T_FLOAT = 0x40;
    const T_EMAIL = 0x80;
    const T_STRING = 0x100;
    const T_DATE = 0x200;
    
    const RESOURCE_TYPES = [
        'wos' => 10027,
    ];
    
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
    
}