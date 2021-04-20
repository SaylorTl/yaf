<?php

class Base
{
    protected $tips;

    public function __construct(){

        $this->tips = new Comm_Curl([ 'service'=>'tips','format'=>'json']);

    }


}
