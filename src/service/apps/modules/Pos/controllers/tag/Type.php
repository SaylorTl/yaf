<?php

final class type extends Base
{
    public function lists ($params = [])
    {
        if (!isTrueKey($params,'page','pagesize')) rsp_die_json(10001, "page、pagesize参数缺失或错误");
        $where = ['page' => $params['page'] ,'pagesize' => $params['pagesize']];
        if (isTrueKey($params,'module_id')) $where['module_id'] = $params['module_id'];
        if (isTrueKey($params,'type_id')) $where['type_id'] = $params['type_id'];
        if (isTrueKey($params,'type_name')) $where['type_name'] = $params['type_name'];

        $data = $this->tag->post('/tag/type/lists',$where);
        if ($data['code'] !== 0) rsp_die_json(10002,$data['message']);
        if (empty($data['content'])) rsp_success_json([],$data['message']);

        $count = $this->tag->post('/tag/type/count',$where);
        $num = $count['code'] == 0 ? $count['content'] : 0 ;

        $tag_module_ids = array_filter(array_unique(array_column($data['content'], 'module_id')));
        $tmp = $this->tag->post('/tag/module/lists',['module_ids'=>implode(',',$tag_module_ids)]);
        $tag_modules = $tmp ? many_array_column($tmp['content'], 'module_id') : [];

        $lists = array_map(function ($m) use ($tag_modules) {
            $m['module_name'] = getArraysOfvalue($tag_modules, $m['module_id'], 'module_name');
           return $m;
        },$data['content']);
        rsp_success_json(['total' => $num,'lists' => $lists],'查询成功');
    }

    public function add($params = []){
        $info = [];
        if(isTruekey($params,'module_id')){
            $info['module_id'] = $params['module_id'];
        }else{
            rsp_die_json(10002,'缺少标签模块id');
        }

        if(isTruekey($params,'type_name')){
            $info['type_name'] = $params['type_name'];
        }else{
            rsp_die_json(10002,'缺少标签类型名称');
        }

        $show = $this->tag->post('/tag/type/show',$info);
        if($show['code'] == 0 && !empty($show['content'])) rsp_die_json(10002,'该标签类型已存在 id:'.$show['content']['type_id']);

        $result = $this->tag->post('/tag/type/add',$info);
        if($result['code'] !=0 ) rsp_die_json(10003,'添加失败');

        rsp_success_json(['type_id'=>$result['content']],'添加成功');
    }


}