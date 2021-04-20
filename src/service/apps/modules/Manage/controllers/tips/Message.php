<?php

final class message extends Base
{

    public function lists ($params = [])
    {
        if (!isTrueKey($params,'page','pagesize')) rsp_die_json(10001, "page、pagesize参数缺失或错误");
        $where = ['page' => $params['page'] ,'pagesize' => $params['pagesize']];
        if (isTrueKey($params,'code_id')) $where['code_id'] = $params['code_id'];
        if (isTrueKey($params,'language')) $where['language'] = $params['language'];

        $data = $this->tips->post('/message/lists',$where);
        if ($data['code'] !== 0) rsp_die_json(10002,$data['message']);
        if (empty($data['content'])) rsp_success_json([],$data['message']);

        $count = $this->tips->post('/message/count',$where);
        $num = $count['code'] == 0 ? $count['content'] : 0 ;

        rsp_success_json(['total' => $num,'lists' => $data['content']],'查询成功');
    }

    public function add($params = []){
        $info = [];
        if(isTruekey($params,'code_id')){
            $info['code_id'] = $params['code_id'];
        }else{
            rsp_die_json(10002,'缺少code_id');
        }

        if(isTruekey($params,'message')){
            $info['message'] = $params['message'];
        }else{
            rsp_die_json(10002,'缺少message');
        }

        if(isTruekey($params,'language')){
            $info['language'] = $params['language'];
        }else{
            $info['language'] = 'CN';
        }

        if(isTruekey($params,'splice_type')){
            $info['splice_type'] = $params['splice_type'];
        }

        $show = $this->tips->post('/message/show',['code_id'=>$info['code_id'],'language'=>$info['language']]);
        if($show['code'] == 0 && !empty($show['content'])) rsp_die_json(10002,'该提示已存在');

        $result = $this->tips->post('/message/add',$info);
        if($result['code'] !=0 ) rsp_die_json(10003,'添加失败');

        rsp_success_json('添加成功');
    }

    public function update($params = []){
        $info = [];
        if(isTruekey($params,'message_id')){
            $info['message_id'] = $params['message_id'];
        }else{
            rsp_die_json(10002,'缺少message_id');
        }

        if(isTruekey($params,'message')){
            $info['message'] = $params['message'];
        }

        if(isTruekey($params,'splice_type')){
            $info['splice_type'] = $params['splice_type'];
        }

        if(isTruekey($params,'language')){
            $info['language'] = $params['language'];
        }

        $result = $this->tips->post('/message/update',$info);
        if($result['code'] !=0 ) rsp_die_json(10003,'更新失败');

        rsp_success_json('更新成功');

    }


}