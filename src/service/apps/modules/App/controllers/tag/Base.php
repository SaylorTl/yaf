<?php

class Base
{
    protected $tag;

    public function __construct(){

        $this->tag = new Comm_Curl([ 'service'=>'tag','format'=>'json']);

    }


}
