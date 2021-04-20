<?php

class Base
{
    protected $log;

    public function __construct(){
        $this->log = new Comm_Curl([ 'service'=>'log','format'=>'json']);
        $this->user = new Comm_Curl([ 'service'=>'user','format'=>'json']);
    }

}
