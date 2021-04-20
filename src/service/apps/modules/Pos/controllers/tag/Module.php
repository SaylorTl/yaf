<?php

final class module extends Base
{
    public function lists ($params = [])
    {
        if (!isTrueKey($params,'page','pagesize')) rsp_die_json(10001, "page、pagesize参数缺失或错误");
        $where = ['page' => $params['page'] ,'pagesize' => $params['pagesize']];
        if (isTrueKey($params,'module_id')) $where['module_id'] = $params['module_id'];

        $data = $this->tag->post('/tag/module/lists',$where);
        if ($data['code'] !== 0) rsp_die_json(10002,$data['message']);
        if (empty($data['content'])) rsp_success_json([],$data['message']);

        $count = $this->tag->post('/tag/module/count',$where);
        $num = $count['code'] == 0 ? $count['content'] : 0 ;

        rsp_success_json(['total' => $num,'lists' => $data['content']],'查询成功');
    }

    public function add($params = []){
        $info = [];
        if(isTruekey($params,'module_name')){
            $info['module_name'] = $params['module_name'];
        }else{
            rsp_die_json(10002,'缺少标签模块名称');
        }

        if(isTruekey($params,'parent')){
            $info['parent'] = $params['parent'];
        }else{
            $info['parent'] = 2;
        }

        $show = $this->tag->post('/tag/module/show',$info);
        if($show['code'] == 0 && !empty($show['content'])) rsp_die_json(10002,'该标签模块已存在 id:'.$show['content']['module_id']);

        $result = $this->tag->post('/tag/module/add',$info);
        if($result['code'] !=0 ) rsp_die_json(10003,'添加失败');

        rsp_success_json(['module_id'=>$result['content']],'添加成功');

    }


}