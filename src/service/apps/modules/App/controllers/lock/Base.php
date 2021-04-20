<?php

class Base
{

    protected $pm;

    protected $user;

    protected $device;

    public function __construct()
    {
        $this->pm = new Comm_Curl(['service' => 'pm', 'format' => 'json']);
        $this->user = new Comm_Curl(['service' => 'user', 'format' => 'json']);
        $this->device = new Comm_Curl(['service' => 'device', 'format' => 'json']);
    }
}

