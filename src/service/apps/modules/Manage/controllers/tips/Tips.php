<?php

final class tips extends Base
{
    public function lists ($params = [])
    {
        if (!isTrueKey($params,'page','pagesize')) rsp_die_json(10001, "page、pagesize参数缺失或错误");
        $where = ['page' => $params['page'] ,'pagesize' => $params['pagesize']];
        if (isset($params['code'])) $where['code'] = $params['code'];
        if (isset($params['language'])) $where['language'] = $params['language'];

        $data = $this->tips->post('/message/lists',$where);
        if ($data['code'] !== 0) rsp_die_json(10002,$data['message']);
        if (empty($data['content'])) rsp_success_json([],$data['message']);

        $count = $this->tips->post('/message/count',$where);
        $num = $count['code'] == 0 ? $count['content'] : 0 ;

        $code_ids = array_filter(array_unique(array_column($data['content'], 'code_id')));
        $tmp = $this->tips->post('/code/lists',['code_ids'=>$code_ids]);
        $codes = $tmp ? many_array_column($tmp['content'], 'type_id') : [];

        $lists = array_map(function ($m) use ($codes) {
            $m['code'] = getArraysOfvalue($codes, $m['code_id'], 'code');
           return $m;
        },$data['content']);

        rsp_success_json(['total' => $num,'lists' => $lists],'查询成功');
    }


    public function cache_clean(){
        $this->tips->post('/tips/cache/clean',[]);
        rsp_success_json(1);
    }
}