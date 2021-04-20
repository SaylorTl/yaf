<?php
include_once APP_PATH . "/BaseController.php";

class Base{
    public $user;
    public $tag;
    public $pm;
    public $access;
    public $face;
    public $file;
    public $device;
    public $msg;
    public $wos;
    public $order;
    public $oauth_app_id;
    public function __construct(){
        $this->oauth_app_id = $_SESSION['oauth_app_id']??"";
        $this->user = new Comm_Curl([ 'service'=> 'user','format'=>'json']);
        $this->tag = new Comm_Curl([ 'service'=>'tag','format'=>'json']);
        $this->pm = new Comm_Curl([ 'service'=>'pm','format'=>'json']);
        $this->resource = new Comm_Curl([ 'service'=>'resource','format'=>'json']);
        $this->access = new Comm_Curl([ 'service'=>'access','format'=>'json']);
        $this->route = new Comm_Curl([ 'service'=>'route','format'=>'json']);
        $this->auth2 = new Comm_Curl([ 'service'=>'auth2','format'=>'json']);
        $this->face = new Comm_Curl([ 'service'=>'face','format'=>'json']);
        $this->file = new Comm_Curl(['service' => 'fileupload', 'format' => 'json']);
        $this->device = new Comm_Curl(['service' => 'device', 'format' => 'json']);
        $this->wos = new Comm_Curl(['service' => 'wos', 'format' => 'json','header' => ["Content-Type: application/json"]]);
        $this->msg = new Comm_Curl([ 'service'=>'msg','format'=>'json']);
        $this->order = new Comm_Curl([ 'service'=> 'order','format'=>'json']);
        $this->tiding = new Comm_Curl(['service' => 'tiding', 'format' => 'json']);

        $this->msg = new Comm_Curl([ 'service'=>'msg','format'=>'json']);

//        if(empty($_SESSION['employee_id'])){

//            rsp_die_json(10001,'用户未登录');
//        }
    }
}