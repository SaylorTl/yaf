<?php

final class Visitor extends Base
{
    /**
     * @param array $post
     * @throws Exception
     * 访客添加
     */
    public function VisitorAdd($post = [])
    {
        $check_params_info = checkEmptyParams($post, ['project_id', 'real_name', 'sex', 'mobile', 'space_id', 'device_id']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $visit_res = $this->resource->post('/resource/id/generator', ['type_name' => 'visitor']);
        if ($visit_res['code'] != 0 || ($visit_res['code'] == 0 && empty($visit_res['content']))) {
            rsp_die_json(10001, $visit_res['message']);
        }

        if (isset($post['visitor_extra']) && $post['visitor_extra']) {
            if (is_not_json($post['visitor_extra'])) {
                rsp_die_json(10001, 'visitor_extra格式错误');
            }
        }
        $post['visit_id'] = $visit_res['content'];
        $post['creator'] = !empty($_SESSION['employee_id']) ? $_SESSION['employee_id'] : '';
        $post['editor'] = !empty($_SESSION['employee_id']) ? $_SESSION['employee_id'] : '';

        $post['authorizer'] = '';
        $post['in_time'] = date('Y-m-d H:i:s');

        $result = $this->user->post('/visitor/useradd', $post);
        if ($result['code'] != 0) {
            rsp_die_json(10002, $result['message']);
        }
        rsp_success_json($result['content'], '添加成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 访客列表
     */
    public function VisitorList($post = [])
    {
        $user_id = $_SESSION['user_id'] ?: '';
        if (!$user_id) {
            rsp_die_json(10001, '用户信息缺失');
        }
//        $user_id = 30;
        $post['user_id'] = $user_id;
        $result = $this->user->post('/visitor/userGroupList', $post);
        if ($result['code'] != 0) {
            rsp_die_json(10002, $result['message']);
        }
        if ($result['code'] == 0 && empty($result['content'])) {
            rsp_success_json(['lists' => [], 'total' => 0], '查询成功');
        }
        $lists = $result['content']['lists'];
        $project_arr = array_unique(array_filter(array_column($lists, 'project_id')));
        $project_res = $this->pm->post('/project/lists', ['project_ids' => $project_arr]);
        $project_content = $project_res['code'] == 0 && $project_res['content'] ? array_column($project_res['content'], null, 'project_id') : [];

        $device_arr = array_unique(array_filter(array_column($lists, 'device_id')));
        $device_res = $this->device->post('/device/lists', ['device_ids' => $device_arr]);
        $device_content = $device_res['code'] == 0 && $device_res['content'] ? array_column($device_res['content'], null, 'device_id') : [];
        // 模板信息
        $device_template_arr = array_unique(array_filter(array_column($device_res['content'], 'device_template_id')));
        $device_templates_res = $this->device->post('/device/template/lists', ['device_template_ids' => $device_template_arr]);
        $device_templates_content = ($device_templates_res['code'] === 0 && $device_templates_res['content']) ? many_array_column($device_templates_res['content'], 'device_template_id') : [];

        $lists = array_map(function ($m) use ($project_content, $device_content, $device_templates_content) {
            $m['visitor_extra'] = isset($m['visitor_extra']) && $m['visitor_extra'] ? json_decode($m['visitor_extra'], true) : [];
            $m['device_params'] = getArraysOfvalue($device_content, $m['device_id'], 'device_params') ?: [];
            $m['project_name'] = getArraysOfvalue($project_content, $m['project_id'], 'project_name');;

//            $m['key'] = '';
            $m['mac'] = '';
            $device_vendor_detail = getArraysOfvalue($device_content, $m['device_id'], 'device_vendor_detail');
            if ($device_vendor_detail) {
                $tmp = json_decode($device_vendor_detail, true);
//                $m['key'] = $tmp['key'] ?? '';
                $m['mac'] = $tmp['mac'] ?? '';
            }

            $m['device_name'] = getArraysOfvalue($device_content, $m['device_id'], 'device_name');
            $m['device_template_id'] = getArraysOfvalue($device_content, $m['device_id'], 'device_template_id');

            $m['vendor_id'] = getArraysOfvalue($device_templates_content, $m['device_template_id'], 'vendor_id');
            $m['device_template_ability_tag_ids'] = getArraysOfvalue($device_templates_content, $m['device_template_id'], 'device_ability_tag_ids');
            $m['device_template_type_tag_id'] = getArraysOfvalue($device_templates_content, $m['device_template_id'], 'device_template_type_tag_id');
            return $m;
        }, $lists);
        rsp_success_json(['total' => count($lists), 'lists' => $lists], '查询成功');
    }

    /**
     * 访客详情
     * @param array $post
     */
    public function VisitorShow($post = [])
    {
        $user_id = $_SESSION['user_id'] ?: '';
        if (!$user_id) {
            rsp_die_json(10001, '用户信息缺失');
        }

        $post['user_id'] = $user_id;

        $result = $this->user->post('/visitor/userlist', array_merge($post, ['page' => 1, 'pagesize' => 1]));
        if ($result['code'] != 0) {
            rsp_die_json(10002, $result['message']);
        }
        if ($result['code'] == 0 && empty($result['content'])) {
            rsp_success_json([], '查询成功');
        }
        
        $show = current($result['content']['lists']);
        $project_res = $this->pm->post('/project/lists', ['project_ids' => [$show['project_id']]]);
        $project_content = $project_res['code'] == 0 && $project_res['content'] ? current($project_res['content']) : [];
        
        $show['project_name'] = $project_content['project_name'] ?? '';
        $show['visitor_extra'] = $show['visitor_extra'] ? json_decode($show['visitor_extra'], true) : [];
        rsp_success_json($show, '查询成功');
    }
    
    /**
     * 设备授权信息
     * @param  array  $post
     */
    public function deviceAuthShow($post = [])
    {
        $user_id = $_SESSION['user_id'] ?: '';
        if (!$user_id) {
            rsp_die_json(10001, '用户信息缺失');
        }
        if (!isTrueKey($post, 'project_id', 'device_id','mobile')) {//mobile暂时没用上
            rsp_die_json(10001, 'project_id、device_id或mobile参数缺失');
        }
        //查询设备信息
        $res = $this->device->post('/device/lists', ['device_id' => $post['device_id']]);
        if (!isset($res['code']) || $res['code'] != 0) {
            rsp_die_json(10002, '设备信息查询失败'.(empty($res['message']) ? '' : ','.$res['message']));
        } elseif (empty($res['content'])) {
            rsp_die_json(90002, '无效的设备ID（device_id）');
        }
        $device_info = array_pop($res['content']);
        $device_details = json_decode($device_info['device_vendor_detail'] ?? '[]',true);
        if (empty($device_details)) {
            rsp_die_json(10007, '设备关键信息缺失');
        }
        $data = [
            'mac' => '',
            'key' => '',
            'valid_count' => null,
            'expire_time' => 0,
        ];
        //检查当前用户是否有当前设备的权限
        $device_auth = $this->checkTenementDeviceAuth($user_id, $post['device_id']);
        if ($device_auth === false) {
            rsp_die_json(10002, '设备授权信息查询失败');
        } elseif ($device_auth) {
            $data['mac'] = $device_details['mac'] ?? '';
            $data['key'] = $device_details['key'] ?? '';
            rsp_success_json($data);
        }
        //查询到访申请记录
        $res = $this->user->post('/visitorapply/lists', [
            'project_id' => $post['project_id'],
            'apply_user_id' => $user_id,
            'check_status_tag_id' => 1170,
            'apply_status_tag_id' => 1166,
        ]);
        if (!isset($res['code']) || $res['code'] != 0) {
            rsp_die_json(10002, '到访申请记录查询失败'.(empty($res['message']) ? '' : ','.$res['message']));
        } elseif (empty($res['content'])) {
            rsp_die_json(10008, '没有找到相关的到访申请记录');
        }
        //过滤无效的到访申请记录
        $visitor_apply_info = $this->filterVisitorApplyInfo($res['content']);
        if (empty($visitor_apply_info)) {
            rsp_die_json(10008, '没有有效的到访申请');
        } elseif (count($visitor_apply_info) > 1) {
            rsp_die_json(10007, '存在多个有效的到访申请信息，无法处理');
        }
        $visitor_apply_info = array_pop($visitor_apply_info);
        //查询到访申请设备授权信息
        $res = $this->user->post('/visitordevice/lists', [
            'visitor_apply_id' => $visitor_apply_info['visitor_apply_id'],
            'device_id' => $post['device_id']
        ]);
        if (!isset($res['code']) || $res['code'] != 0) {
            rsp_die_json(10002, '设备授权查询失败'.(empty($res['message']) ? '' : ','.$res['message']));
        } elseif (empty($res['content'])) {
            rsp_die_json(10008, '没有找到相关的设备授权信息');
        }
        $visitor_device_info = array_pop($res['content']);
        $data = [
            'valid_count' => $visitor_device_info['valid_count'],
            'expire_time' => $visitor_device_info['expire_time']
                ? date('Y-m-d H:i:s',$visitor_device_info['expire_time'])
                : '' ,
        ];
        $time = time();
        if (
            (!$visitor_device_info['expire_time'] || $visitor_device_info['expire_time'] >= $time)
            && $visitor_device_info['valid_count'] > 0
        ) {
            $data['mac'] = $device_details['mac'] ?? '';
            $data['key'] = $device_details['key'] ?? '';
        }
        rsp_success_json($data);
    }
    
    /**
     * 检查住户的设备授权
     * @param $user_id
     * @param $device_id
     * @return bool|int 1：有权限，0：没有权限，false：服务异常
     */
    private function checkTenementDeviceAuth($user_id, $device_id)
    {
        $res = $this->user->post('/userdevice/lists', [
            'user_id' => $user_id,
            'device_id' => $device_id,
        ]);
        if (!isset($res['code']) || $res['code'] != 0) {
            log_message('---Visitor/'.__FUNCTION__.'---error:'.json_encode($res.JSON_UNESCAPED_UNICODE));
            return false;
        } elseif (array_filter($res['content'])) {
            return 1;
        }
        return 0;
    }
    
    /**
     * 过滤已过期的访客记录
     * @param  array  $info
     * @return array|bool
     */
    private function filterVisitorApplyInfo($info)
    {
        $res = [];
        $time = time();
        foreach ($info as $item) {
            if (!$item['expire_time'] || $item['expire_time'] >= $time) {
                $res[] = $item;
            }
        }
        if (empty($res)) {
            info('---Visitor/'.__FUNCTION__.'---', [
                'msg' => '没有有效的到访申请记录',
            ]);
        } elseif (count($res) > 1) {
            info('---Visitor/'.__FUNCTION__.'---', [
                'msg' => '存在多条生效的申请记录',
                'data' => $res,
            ]);
        }
        return $res;
    }
    
}