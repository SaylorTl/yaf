<?php

final class Sending extends Base
{
    public function sendmsg($params = []){
        if( !isTrueKey($params,'channel','title','source') ){
            rsp_die_json(10001,'请求信息不全');
        }
        $sendResult = $this->msg->post('/pushmsg/singleUser',$params);
        if( empty($sendResult) ){
            rsp_die_json(10008,'请求接口响应异常');
        }
        if( $sendResult['code'] != 0 ){
            rsp_die_json(10007,$sendResult['message']);
        }
        rsp_success_json($sendResult['content'],'请求成功');
    }

}