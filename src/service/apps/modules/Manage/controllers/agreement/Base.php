<?php

class Base
{
    const RESOURCE_TYPES = [
        'agreement'                 => 10004,
        'agreement_template'        => 10008,
    ];

    protected $agreement;

    protected $company;

    protected $pm;

    protected $user;

    protected $fileupload;

    protected $employee_id;

    public function __construct(){
        $this->agreement = new Comm_Curl([ 'service'=>'agreement','format'=>'json']);
        $this->company = new Comm_Curl([ 'service'=>'company','format'=>'json']);
        $this->pm = new Comm_Curl([ 'service'=>'pm','format'=>'json']);
        $this->user = new Comm_Curl([ 'service'=> 'user','format'=>'json']);
        $this->fileupload = new Comm_Curl([ 'service'=>'fileupload','format'=>'json']);
        $this->employee_id = $_SESSION['employee_id'] ?? '';
    }

}
