<?php
include_once APP_PATH . "/BaseController.php";

class Base{
    public $user;
    public $tag;
    public $pm;
    public $access;
    public function __construct(){
        $this->user = new Comm_Curl([ 'service'=> 'user','format'=>'json']);
        $this->tag = new Comm_Curl([ 'service'=>'tag','format'=>'json']);
        $this->pm = new Comm_Curl([ 'service'=>'pm','format'=>'json']);
        $this->resource = new Comm_Curl([ 'service'=>'resource','format'=>'json']);
        $this->access = new Comm_Curl([ 'service'=>'access','format'=>'json']);
        $this->route = new Comm_Curl([ 'service'=>'route','format'=>'json']);
        $this->auth2 = new Comm_Curl([ 'service'=>'auth2','format'=>'json']);
//        if(empty($_SESSION['employee_id'])){

//            rsp_die_json(10001,'用户未登录');
//        }
    }
}