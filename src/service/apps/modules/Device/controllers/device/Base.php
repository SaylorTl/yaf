<?php

class Base {

	protected $device;

	protected $pm;

	protected $user;

	protected $file;

    protected $face;

    protected $resource;

    protected $project_id;

    protected $car;

    protected $contract;

	public function __construct(){
		$this->device = new Comm_Curl([ 'service'=>'device','format'=>'json']);
        $this->user = new Comm_Curl([ 'service'=>'user','format'=>'json']);
        $this->pm = new Comm_Curl([ 'service'=>'pm','format'=>'json']);
        $this->file = new Comm_Curl([ 'service'=>'fileupload','format'=>'json']);
        $this->face = new Comm_Curl([ 'service'=>'face','format'=>'json']);
        $this->resource = new Comm_Curl([ 'service'=>'resource','format'=>'json']);
        $this->project_id = $_SESSION['member_project_id'] ?? '';
        $this->car = new Comm_Curl([ 'service'=>'car','format'=>'json']);
        $this->contract = new Comm_Curl([ 'service'=>'contract','format'=>'json']);
	}
}