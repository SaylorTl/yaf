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
        $post = $this->getRequest()->getPost();
        $permission_key = !empty($_GET['permissions_key'])?$_GET['permissions_key']:'';
        (new AuthModel())->checkPrivleges($post,$permission_key);
        parent::init();
        $lists = parse_controller_action($this->getRequest()->getActionName());
        if(4==count($lists)){
            list($controller,$dir,$class,$action) = $lists;
            include __DIR__ . '/'.strtolower($controller).'/Base.php';
            include __DIR__ . '/'.strtolower($controller).'/' .$dir.'/' . ucfirst($class).".php";
        }else{
            list($controller,$class,$action) = $lists;
            include __DIR__ . '/'.strtolower($controller).'/Base.php';
            include __DIR__ . '/'.strtolower($controller).'/' . ucfirst($class).".php";
        }
        if (!class_exists($class)){
            rsp_die_json(90002, 'Class does not exist');
        }
        if (!method_exists($class,$action)){
            rsp_die_json(90002, 'Method does not exist');
        }
        (new $class())->$action($this->getRequest()->getParams());
    }

}
