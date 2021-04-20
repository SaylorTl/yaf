<?php

class Base {

    const REDIS_KEYS = [
        'ys_token' => 'device:video:ys:token',
    ];

	protected $device;

    protected $project_id;

	public function __construct(){
		$this->device = new Comm_Curl([ 'service'=>'device','format'=>'json']);
        $this->project_id = $_SESSION['member_project_id'] ?? '';
	}
}