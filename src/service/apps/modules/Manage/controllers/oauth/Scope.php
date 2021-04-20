<?php


final class Scope extends Base
{
    public function lists($query)
    {
        $result = (new Comm_Gateway())->gateway($query,'admin.scope.lists',self::SERVICE);
        if( $result['code'] != 0 ){
            rsp_die_json(10007,$result['message']);
        }
        rsp_success_json($result['content'], '查询成功');
    }

    public function show($query)
    {
        $result = (new Comm_Gateway())->gateway($query,'admin.scope.show',self::SERVICE);
        if( $result['code'] != 0 ){
            rsp_die_json(10007,$result['message']);
        }
        rsp_success_json($result['content'], '查询成功');
    }
}