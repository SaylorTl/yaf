<?php

final class prefix extends Base
{
    public function lists ($params = [])
    {
        if (!isTrueKey($params,'page','pagesize')) rsp_die_json(10001, "page、pagesize参数缺失或错误");
        $where = ['page' => $params['page'] ,'pagesize' => $params['pagesize']];
        if (isTrueKey($params,'prefix')) $where['prefix'] = $params['prefix'];

        $data = $this->tips->post('/prefix/lists',$where);
        if ($data['code'] !== 0) rsp_die_json(10002,$data['message']);
        if (empty($data['content'])) rsp_success_json([],$data['message']);

        $count = $this->tips->post('/prefix/count',$where);
        $num = $count['code'] == 0 ? $count['content'] : 0 ;

        rsp_success_json(['total' => $num,'lists' => $data['content']],'查询成功');
    }

    public function add($params = []){
        $info = [];
        if(isTruekey($params,'prefix')){
            $info['prefix'] = $params['prefix'];
        }else{
            rsp_die_json(10002,'缺少prefix');
        }

        if(isTruekey($params,'remark')){
            $info['remark'] = $params['remark'];
        }

        $show = $this->tips->post('/prefix/show',['prefix'=>$info['prefix']]);
        if($show['code'] == 0 && !empty($show['content'])) rsp_die_json(10002,'前缀码'.$info['prefix'].'已存在');

        $result = $this->tips->post('/prefix/add',$info);
        if($result['code'] !=0 ) rsp_die_json(10003,'添加失败');

        rsp_success_json('添加成功');

    }

    public function update($params = []){
        $info = [];
        if(isTruekey($params,'prefix_id')){
            $info['prefix_id'] = $params['prefix_id'];
        }else{
            rsp_die_json(10002,'缺少prefix_id');
        }

        if(isTruekey($params,'remark')){
            $info['remark'] = $params['remark'];
        }

        $result = $this->tips->post('/prefix/update',$info);
        if($result['code'] !=0 ) rsp_die_json(10003,'更新失败');

        rsp_success_json('更新成功');

    }


}