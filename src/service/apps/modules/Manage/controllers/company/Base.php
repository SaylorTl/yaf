<?php

class Base
{
    protected $company;
    protected $tag;
    protected $addr;
    protected $employee_id;
    protected $user;
    const RESOURCE_TYPES = [
        'company'      => 10005
    ];

    public function __construct(){
        $this->company = new Comm_Curl([ 'service'=>'company','format'=>'json']);
		$this->tag = new Comm_Curl([ 'service'=>'tag','format'=>'json']);    
		$this->addr = new Comm_Curl([ 'service'=>'addr','format'=>'json']); 
        $this->user = new Comm_Curl([ 'service'=> 'user','format'=>'json']);
        $this->employee_id = $_SESSION['employee_id'] ?? ''; 
	}
}
