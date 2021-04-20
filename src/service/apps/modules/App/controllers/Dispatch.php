<?php
include_once APP_PATH."/BaseController.php";

/**
 * 统一分发入口
 * 路由配置示例
 * controller固定值dispatch，action格式'微服务/模块/方法'
 * 'et_admin.car.color.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'car/color/lists'],
 * Class DispatchController
 */
class DispatchController extends BaseController{
    public function init(){
        parent::init();
        list($controller,$class,$action) = parse_controller_action($this->getRequest()->getActionName());
        include __DIR__ . '/'.strtolower($controller).'/Base.php';
        include __DIR__ . '/'.strtolower($controller).'/' . ucfirst($class).".php";
        if (!class_exists($class)){
            rsp_die_json(90002, 'Class does not exist');
        }
        if (!method_exists($class,$action)){
            rsp_die_json(90002, 'Method does not exist');
        }
        (new $class())->$action($this->getRequest()->getParams());
    }
}