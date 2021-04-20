<?php

class BaseController extends Yaf_Controller_Abstract {
    public  $access;
    public $route;

    public function init(){
        Yaf_Dispatcher::getInstance()->disableView();
    }

    protected function getToken(){
        return session_id();
    }

}
