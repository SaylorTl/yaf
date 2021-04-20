<?php

class Base
{
    const RESOURCE_TYPES = [
        'project'      => 10001,
        'space'        => 10006,
        'park_place'   => 10007,
        'house'        => 10010,
        'frame'        => 10002,
        'device'       => 10009,
        'cells'       => 10015,
        'repair'       => 10019,
        'readmeter'       => 10020,
        'mediation'       => 10024,
        'facility'       => 10021,
        'plants'       => 10022,
        'yardrent'       => 10023,
    ];

    protected $pm;

    protected $tag;

    protected $company;

    protected $user;

    protected $agreement;

    protected $fileupload;

    protected $employee_id;

    public function __construct(){
        $this->pm = new Comm_Curl([ 'service'=>'pm','format'=>'json']);
        $this->tag = new Comm_Curl([ 'service'=>'tag','format'=>'json']);
        $this->company = new Comm_Curl([ 'service'=>'company','format'=>'json']);
        $this->user = new Comm_Curl([ 'service'=> 'user','format'=>'json']);
        $this->agreement = new Comm_Curl([ 'service'=>'agreement','format'=>'json']);
        $this->fileupload = new Comm_Curl([ 'service'=>'fileupload','format'=>'json']);
        $this->employee_id = $_SESSION['employee_id'] ?? '';
    }

}

