<?php

final class code extends Base
{
    public function lists ($params = [])
    {
        if (!isTrueKey($params,'page','pagesize')) rsp_die_json(10001, "page、pagesize参数缺失或错误");
        $where = ['page' => $params['page'] ,'pagesize' => $params['pagesize']];
        if (isTrueKey($params,'code')) $where['code'] = $params['code'];

        $data = $this->tips->post('/code/lists',$where);
        if ($data['code'] !== 0) rsp_die_json(10002,$data['message']);
        if (empty($data['content'])) rsp_success_json([],$data['message']);

        $count = $this->tips->post('/code/count',$where);
        $num = $count['code'] == 0 ? $count['content'] : 0 ;

        rsp_success_json(['total' => $num,'lists' => $data['content']],'查询成功');
    }

    public function add($params = []){
        $info = [];
        if(isTruekey($params,'code')){
            $info['code'] = $params['code'];
        }else{
            rsp_die_json(10002,'缺少code');
        }

        if(isTruekey($params,'prefix_id')){
            $info['prefix_id'] = $params['prefix_id'];
        }

        if(isTruekey($params,'splice_type')){
            $info['splice_type'] = $params['splice_type'];
        }

        $show = $this->tips->post('/code/show',['code'=>$info['code']]);
        if($show['code'] == 0 && !empty($show['content'])) rsp_die_json(10002,'状态码'.$info['code'].'已存在');

        $result = $this->tips->post('/code/add',$info);
        if($result['code'] !=0 ) rsp_die_json(10003,'添加失败');

        rsp_success_json('添加成功');

    }

    public function update($params = []){
        $info = [];
        if(isTruekey($params,'code_id')){
            $info['code_id'] = $params['code_id'];
        }else{
            rsp_die_json(10002,'缺少code_id');
        }

        if(isTruekey($params,'code')){
            $info['code'] = $params['code'];
        }

        if(isTruekey($params,'splice_type')){
            $info['splice_type'] = $params['splice_type'];
        }

        if(isTruekey($params,'prefix_id')){
            $info['prefix_id'] = $params['prefix_id'];
        }

        $result = $this->tips->post('/code/update',$info);
        if($result['code'] !=0 ) rsp_die_json(10003,'更新失败');

        rsp_success_json('更新成功');

    }


}