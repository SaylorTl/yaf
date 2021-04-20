<?php
final class Common extends Base {

    public function province_lists(){
        $lists = $this->addr->post('/province/lists');
        if ($lists['code'] !== 0) rsp_die_json(10001, $lists['message']);
        rsp_success_json($lists['content']);
    }

    public function city_lists($params = []){
        if( !isTrueKey($params,'province_code') ) rsp_die_json(10001, "province_code 参数缺失或错误");

        $lists = $this->addr->post('/city/lists',['province_code'=>$params['province_code']]);

        if ($lists['code'] !== 0) rsp_die_json(10001, $lists['message']);
        rsp_success_json($lists['content']);
    }

    public function area_lists($params = []){
        if( !isTrueKey($params,'city_code') ) rsp_die_json(10001, "city_code 参数缺失或错误");

        $lists = $this->addr->post('/area/lists', ['city_code'=>$params['city_code']]);

        if ($lists['code'] !== 0) rsp_die_json(10001, $lists['message']);
        rsp_success_json($lists['content']);
    }

    public function street_lists($params = []){
        if( !isTrueKey($params,'area_code') ) rsp_die_json(10001, "area_code 参数缺失或错误");

        $lists = $this->addr->post('/street/lists', ['area_code'=>$params['area_code']]);

        if ($lists['code'] !== 0) rsp_die_json(10001, $lists['message']);
        rsp_success_json($lists['content']);
    }

    public function sub_lists($params = []){
        if( !isTrueKey($params,'code') ) rsp_die_json(10001, "code 参数缺失或错误");

        $lists = $this->addr->post('/sub/lists', ['code'=>$params['code']]);

        if ($lists['code'] !== 0) rsp_die_json(10001, $lists['message']);
        rsp_success_json($lists['content']);
    }

    public function parent_show($params = []){
        if( !isTrueKey($params,'code') ) rsp_die_json(10001, "code 参数缺失或错误");

        $data = $this->addr->post('/addrcode/show', ['code'=>$params['code']]);

        if ($data['code'] !== 0) rsp_die_json(10001, $data['message']);
        rsp_success_json($data['content']);
    }

    public function tree($params = []){
        if( !isTrueKey($params,'level') ) rsp_die_json(10001, "level 参数缺失或错误");
        $level = (int)$params['level'];
        $level = ($level > 0 && $level < 4) ? $level : 4;

        $lists = $this->addr->post('/tree', ['level'=>$level]);
        if ($lists['code'] !== 0) rsp_die_json(10001, $lists['message']);
        rsp_success_json($lists['content']);
    }

    public function codes($params){
        if( !isTrueKey($params,'codes') ) rsp_die_json(10001, "codes 参数缺失或错误");

        $data = $this->addr->post('/addrcode/codes', ['codes'=>$params['codes']]);
        if ($data['code'] !== 0) rsp_die_json(10001, $data['message']);
        rsp_success_json($data['content']);
    }
}
