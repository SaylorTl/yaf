<?php
/**
 * Created by PhpStorm.
 * User: 18716
 * Date: 2020/1/6
 * Time: 12:07
 */

namespace Session;

class ConstantModel
{
    /**
     * 登录信息 memberInfo中key对应下列的value
     * 下列的数组中key对应设置session的key
     */
    const MEMBER_COLUMNS = [
        'member_scope' => 'scope',
        'member_id' => 'member_id',
        'member_mobile' => 'mobile',
        'member_nickname' => 'nickname',
        'member_full_name' => 'full_name',
        'member_user_name' => 'user_name',
        'employee_id' => 'employee_id',
        'member_role_id' => 'role_id',
        'member_role_name' => 'role_name',
        'member_p_role_id' => 'p_role_id',
        'access_control_source_id' => 'access_control_source_id',
        'member_project_id' => 'project_id',
        'member_jsfrom_id' => 'jsfrom_id',
        'member_jsfrom_wx_appid' => 'jsfrom_wx_appid',
        'member_jsfrom' => 'jsfrom',
        'employee_frame_id' => 'employee_frame_id',
        'member_token_source' => 'request_token_source'
    ];

    /**
     * 登录信息 appInfo中key对应下列的value
     * 下列的数组中key对应设置session的key
     */
    const APP_COLUMNS = [
        'app_scope' => 'scope',
        'client_id' => 'client_id',
        'user_id' => 'user_id',
        'user_mobile' => 'user_mobile',
        'user_name' => 'user_name',
        'user_full_name' => 'user_full_name',
        'user_nick_name' => 'user_nick_name',
        'session_key' => 'session_key',
        'unionid' => 'unionid',
        'headimgurl' => 'headimgurl',
        'openid' => 'openid',
        'employee_full_name' => 'employee_full_name',
        'employee_nick_name' => 'employee_nick_name',
        'employee_user_name' => 'employee_user_name',
        'employee_user_mobile' => 'employee_user_mobile',
        'third_party_app_id' => 'third_party_app_id',
        'sender_client_id' => 'sender_client_id',
        'jsfrom_id' => 'jsfrom_id',
        'jsfrom_wx_appid' => 'jsfrom_wx_appid',
        'jsfrom' => 'jsfrom',
    ];
}