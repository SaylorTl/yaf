<?php

class Base
{
    protected $pm;
    protected $car;
    protected $contract;
    protected $resource;

    public function __construct()
    {
        $this->car = new Comm_Curl(['service' => 'car', 'format' => 'json']);
        $this->contract = new Comm_Curl(['service' => 'contract', 'format' => 'json']);
        $this->pm = new Comm_Curl(['service' => 'pm', 'format' => 'json']);
        $this->resource = new Comm_Curl(['service' => 'resource', 'format' => 'json']);
    }
}
