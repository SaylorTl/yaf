<?php

class Base
{
    protected $token_url;

    public function __construct(){

        $this->token_url = new Comm_Curl([ 'service'=>'wxtoken','format'=>'json']);
    }
    
}
