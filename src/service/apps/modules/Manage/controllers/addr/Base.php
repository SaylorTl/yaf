<?php

class Base
{
    protected $addr;

    public function __construct(){
        $this->addr = new Comm_Curl([ 'service'=>'addr','format'=>'json']);
    }
}
