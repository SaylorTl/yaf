<?php
require BASE_PATH.'/service/library/Events/Auth.php';        //全局通用函数
class Base
{
    protected $resource;
    protected $access;
    protected $route;
    protected $user;
    protected $pm;
    protected $tag;

    public function __construct()
    {
        $this->user = new Comm_Curl([ 'service'=> 'user','format'=>'json']);
        $this->tag = new Comm_Curl([ 'service'=>'tag','format'=>'json']);
        $this->pm = new Comm_Curl([ 'service'=>'pm','format'=>'json']);
        $this->resource = new Comm_Curl([ 'service'=>'resource','format'=>'json']);
        $this->access = new Comm_Curl([ 'service'=>'access','format'=>'json']);
        $this->route = new Comm_Curl([ 'service'=>'route','format'=>'json']);
    }
}
