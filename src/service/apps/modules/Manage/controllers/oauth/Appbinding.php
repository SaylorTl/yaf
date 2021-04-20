<?php


final class Appbinding extends Base
{
    public function lists($params = [])
    {
        $post = [
            'oauth_app_id'      => $_SESSION['oauth_app_id'] ?? '',
            'binding_status'    => 'Y',
            'not_limit_page'    => 'Y',
        ];
        if (isTrueKey($params, 'name_zh')) $post['name_zh'] = $params['name_zh'];
        if (isTrueKey($params, 'name_en')) $post['name_en'] = $params['name_en'];
        if (isTrueKey($params, 'app_type')) $post['app_type'] = $params['app_type'];
        $result = (new Comm_Gateway())->gateway($post,'admin.appbinding.lists',self::SERVICE);
        if( $result['code'] != 0 ){
            rsp_die_json(10007,$result['message']);
        }
        rsp_success_json($result['content'], '查询成功');
    }
}