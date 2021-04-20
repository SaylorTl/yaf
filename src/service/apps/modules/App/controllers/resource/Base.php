<?php

class Base
{
    protected $resource;

    public function __construct(){

        $this->resource = new Comm_Curl([ 'service'=>'resource','format'=>'json']);
    }
    
}
