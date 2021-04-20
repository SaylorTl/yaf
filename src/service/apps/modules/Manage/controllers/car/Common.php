<?php
final class Common extends Base {

    public function brand_lists($params = []){
        $arg = [];
        if( isTrueKey($params,'brand_name') ) $arg['brand_name'] = $params['brand_name'];

        $lists = $this->car->post('/car/brand/lists',$arg);
        if ($lists['code'] !== 0) rsp_die_json(10001, $lists['message']);
        rsp_success_json($lists['content']);
    }

    public function type_lists($params = []){
        if( !isTrueKey($params,'brand_id') ) rsp_die_json(10001, "brand_id 参数缺失或错误");

        $lists = $this->car->post('/car/type/lists', ["brand_id"=>$params["brand_id"]]);
        if ($lists['code'] !== 0) rsp_die_json(10001, $lists['message']);
        rsp_success_json($lists['content']);
    }
}
