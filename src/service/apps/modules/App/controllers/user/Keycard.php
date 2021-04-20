<?php

final class Keycard extends Base
{
    public function infoAdd($post = [])
    {
        log_message(__METHOD__ . '-----' . json_encode($post));
        $user_id = $_SESSION['user_id'] ?? '';
        if (!$user_id) {
            rsp_die_json(10002, '用户信息不存在');
        }

        $post['user_id'] = $user_id;
        $post['real_name'] = isset($post['real_name']) ? $post['real_name'] : $_SESSION['user_name'] ?: '访客' . time();
        $check_params_info = checkParams($post, ['project_id', 'mobile', 'sex']);

        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }

        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }

        if (!isTrueKey($post, 'house_id') || (!isset($post['tenement_identify_tag_id']) || !in_array($post['tenement_identify_tag_id'], [916, 917]))) {
            $this->_visitorAdd($post);
        }

        $this->_tenementAdd($post);
    }

    private function _visitorAdd($params)
    {
        $visit_res = $this->resource->post('/resource/id/generator', ['type_name' => 'visitor']);
        if ($visit_res['code'] != 0 || ($visit_res['code'] == 0 && empty($visit_res['content']))) {
            rsp_die_json(10001, $visit_res['message']);
        }

        if (!isTrueKey($params, 'house_id')) {
            rsp_error_tips(10001, 'house_id');
        }
        $houses = $this->pm->post('/house/basic/lists', ['house_id' => $params['house_id']]);
        $houses = $houses['content'] ?? [];
        if (!$houses) {
            rsp_die_json(10002, '房产不存在');
        }

        $add_params = [
            'project_id' => $params['project_id'],
            'mobile' => $params['mobile'],
            'sex' => $params['sex'],
            'device_id' => $params['device_id'] ?? '',
            'visit_id' => $visit_res['content'],
            'creator' => $_SESSION['employee_id'] ?? '',
            'editor' => $_SESSION['employee_id'] ?? '',
            'in_time' => date('Y-m-d H:i:s'),
            'space_id' => $houses[0]['space_id'] ?? '',
            'real_name' => $params['real_name'],
            'authorizer' => ''
        ];
        if (isset($params['visitor_extra']) && $params['visitor_extra']) {
            if (is_not_json($params['visitor_extra'])) {
                rsp_die_json(10001, 'visitor_extra格式错误');
            }
            $add_params['visitor_extra'] = $params['visitor_extra'];
        }

        $result = $this->user->post('/visitor/useradd', $add_params);
        if ($result['code'] != 0) {
            rsp_die_json(10002, $result['message']);
        }
        rsp_success_json($result['content'], '添加成功');
    }


    private function _tenementAdd($params)
    {
        $tenement_params = ['project_id' => $params['project_id'], 'user_id' => $params['user_id'], 'page' => 1, 'pagesize' => 1];
        $result = $this->user->post('/tenement/lists', $tenement_params);
        log_message(__METHOD__ . '------' . json_encode($tenement_params) . '--result=' . json_encode($result));
        if ($result['code'] != 0) {
            rsp_die_json(10002, $result['message']);
        }


        $tenement_id = $result['content'] ? $result['content'][0]['tenement_id'] : '';
        $tenement_identify_tag_id = isset($params['tenement_identify_tag_id']) && in_array($params['tenement_identify_tag_id'], [916, 917]) ? $params['tenement_identify_tag_id'] : '917';
        if ($tenement_id) {
            $update_params = [
                'tenement_id' => $tenement_id,
                'real_name' => $params['real_name'],
                'mobile' => $params['mobile'],
                'tenement_identify_tag_id' => $tenement_identify_tag_id
            ];
            $this->user->post('/tenement/update', $update_params);
        }
        $is_update = !!$tenement_id;

        if (!$result['content']) {
            $tenement_res = $this->resource->post('/resource/id/generator', ['type_name' => 'tenement']);
            if ($tenement_res['code'] != 0 || ($tenement_res['code'] == 0 && empty($tenement_res['content']))) {
                rsp_die_json(10001, '资源id生成失败');
            }

            $tenement_id = $tenement_res['content'];
            $tenement_add_params = [
                'project_id' => $params['project_id'],
                'real_name' => $params['real_name'],
                'sex' => $params['sex'],
                'license_tag_id' => 0,
                'license_num' => 0,
                'mobile' => $params['mobile'],
                'birth_day' => date('Y-m-d'),
                'contact_name' => 0,
                'in_time' => time(),
                'tenement_id' => $tenement_id,
                'tenement_check_status' => 'N',
                'tenement_type_tag_id' => 411,
                'tenement_identify_tag_id' => $tenement_identify_tag_id,
                'customer_type_tag_id' => 196,//一般客户
            ];
            $useradd = $this->user->post('/tenement/useradd', $tenement_add_params);
            if ($useradd['code'] != 0) {
                rsp_die_json(10002, $useradd['message']);
            }
        }

        // 人脸
        if (isTrueKey($params, 'face_resource_id')) {
            $face_model = new \Face\FaceModel();
            $person_exists = $face_model->personExists(['project_id' => $params['project_id'], 'face_resource_id' => $params['face_resource_id']]);
            if ($person_exists && !$is_update) {
                rsp_die_json(10001, '人脸已存在');
            }
            $face_model->refreshFace([
                'tenement_id' => $tenement_id,
                'project_id' => $params['project_id'],
                'face_resource_id' => $params['face_resource_id'],
            ]);

            $this->user->post('/tenement/update', ['tenement_id' => $tenement_id, 'face_resource_id' => $params['face_resource_id']]);
        }

        $house_show = $this->user->post('/house/lists', [
            'tenement_id' => $tenement_id,
            'house_id' => $params['house_id'],
            'page' => 1,
            'pagesize' => 1
        ]);

        if ($house_show['code'] == 0 && !$house_show['content']) {
            if (isset($params['t_house_id']) && $params['t_house_id']) {
                $house_update = ['t_house_id' => $params['t_house_id'], 'house_id' => $params['house_id'], 'tenement_id' => $tenement_id, 'tenement_identify_tag_id' => $tenement_identify_tag_id];
                $this->user->post('/house/update', $house_update);
            } else {
                $result = $this->user->post('/house/add', [
                    'tenement_id' => $tenement_id,
                    'house_id' => $params['house_id'],
                    'tenement_house_status' => 'N',
                    'tenement_identify_tag_id' => $tenement_identify_tag_id,
                    'in_time' => time(),
                    'out_time' => time()
                ]);

                if ($result['code'] != 0) {
                    rsp_die_json(10002, $result['message']);
                }
            }
        } else {
            $house_update = ['t_house_id' => $house_show['content'][0]['t_house_id'], 'house_id' => $params['house_id'], 'tenement_id' => $tenement_id, 'tenement_identify_tag_id' => $tenement_identify_tag_id];
            $this->user->post('/house/update', $house_update);
        }


        if (isTrueKey($params, 'face_resource_id')) {
            (new \Device\DeviceModel())->toggleTenementPrivileges($tenement_id);
        }

        rsp_success_json($result['content'] ?? 1, 'success');
    }


}
