<?php

class Base
{
    const RESOURCE_TYPES = [
        'billing_type' => 10040,
        'billing_account' => 10041,
        'business_config' => 10042,
        'receivable_bill' => 10043,
    ];
    
    protected $billing;
    protected $space;
    protected $tag;
    protected $resource;
    protected $user;
    protected $rule;
    protected $pm;
    
    protected $employee_id;
    protected $project_id;
    
    public function __construct()
    {
        $this->employee_id = $_SESSION['employee_id'] ?? '0';
        $this->project_id = $_SESSION['member_project_id'] ?? '0';
        
        $this->billing = new Comm_Curl(['service' => 'billing', 'format' => 'json',]);
        $this->space = new Comm_Curl(['service' => 'pm', 'format' => 'json']);
        $this->tag = new Comm_Curl(['service' => 'tag', 'format' => 'json']);
        $this->user = new Comm_Curl(['service' => 'user', 'format' => 'json']);
        $this->resource = new Comm_Curl(['service' => 'resource', 'format' => 'json']);
        $this->pm = new Comm_Curl(['service' => 'pm', 'format' => 'json']);
        $this->rule = new Comm_Curl([
            'service' => 'rule',
            'format' => 'json',
            'header' => [
                'Content-Type:application/json',
                'Project-Id:'.$this->project_id,
            ]
        ]);
    }
    
    protected function getTagId($tag_name)
    {
        $res = $this->tag->post('/tag/lists', ['tag_name' => $tag_name, 'type_ids' => [201, 202], 'nolevel' => 'Y']);
        if ($res['code'] != 0) {
            return false;
        }
        $tag_info = array_pop($res['content']);
        return $tag_info['tag_id'] ?? 0;
    }
}
