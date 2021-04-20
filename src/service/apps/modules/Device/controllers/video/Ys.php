<?php

final class Ys extends Base
{
    public function token($params = [])
    {
        $res = $this->device->post('/ysCloud/getToken');
        if( $res['code'] != 0 ){
            rsp_die_json(10002,$res['message']);
        }
        rsp_success_json($res['content']['token'] ?? '');
    }
}