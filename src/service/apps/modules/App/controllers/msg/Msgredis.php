<?php

final class Msgredis extends Base
{
    public function handle($params = []){
        if( !isTrueKey($params,'handle','key') ){
            rsp_die_json(10001,'请求信息不全');
        }
        $sendResult = $this->msg->post('/redis/handle',$params);
        if( empty($sendResult) ){
            rsp_die_json(10008,'请求接口响应异常');
        }
        if( $sendResult['code'] != 0 ){
            rsp_die_json(10007,$sendResult['message']);
        }
        rsp_success_json($sendResult['content'],'请求成功');
    }

}