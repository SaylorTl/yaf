<?php

class Base
{
    protected $car;

    public function __construct(){
        $this->car = new Comm_Curl([ 'service'=>'car','format'=>'json']);
    }
}
