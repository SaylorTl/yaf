<?php

class Base
{
    protected $msg;

    public function __construct(){
        $this->msg = new Comm_Curl([ 'service'=>'msg','format'=>'json']);

    }


}
