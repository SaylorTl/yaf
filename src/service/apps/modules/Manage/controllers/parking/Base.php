<?php

class Base
{
    protected $pm;

    protected $client_id;

    protected $user;

    protected $tag;

    protected $contract;

    protected $car;

    protected $resource;

    protected $rule;

    public function __construct()
    {
        $this->pm = new Comm_Curl([ 'service'=>'pm','format'=>'json']);
        $this->user = new Comm_Curl([ 'service'=> 'user','format'=>'json']);
        $this->tag = new Comm_Curl([ 'service'=> 'tag','format'=>'json']);
        $this->car = new Comm_Curl([ 'service'=> 'car','format'=>'json']);
        $this->client_id = $_SESSION['client_id'] ?? 0;
        $this->project_id = $_SESSION['member_project_id'] ?? '';
        $this->resource = new Comm_Curl(['service' => 'resource', 'format' => 'json']);

        $this->contract = new Comm_Curl([ 'service'=>'contract','format'=>'json']);

        $this->rule = new Comm_Curl([ 'service'=>'rule','format'=>'json']);
    }
}

