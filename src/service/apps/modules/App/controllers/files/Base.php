<?php

class Base
{
    protected $file;
    protected $resource;

    public function __construct()
    {
        $this->file = new Comm_Curl(['service' => 'fileupload', 'format' => 'json']);
        $this->resource = new Comm_Curl(['service' => 'resource', 'format' => 'json']);
    }
}
