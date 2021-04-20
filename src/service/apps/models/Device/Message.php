<?php

namespace Device;

use Device\ConstantModel as Constant;

class MessageModel {

    protected $user;

    protected $pm;

    protected $device;

    protected $file;

    protected $params;

    public function  __construct(Array $params = [])
    {
        $this->user = new \Comm_Curl([ 'service'=> 'user','format'=>'json']);
        $this->pm = new \Comm_Curl([ 'service'=>'pm','format'=>'json']);
        $this->device = new \Comm_Curl(['service' => 'device', 'format' => 'json']);
        $this->file = new \Comm_Curl(['service' => 'fileupload', 'format' => 'json']);
        $this->params = $params;
    }

    public function filter()
    {
        $method = "filter{$this->params['cmd']}";
        if (!method_exists($this, $method)) return $this->params;
        return $this->$method();
    }

    /**
     * 呼叫
     * @return array
     */
    private function filter1174()
    {
        if (!isTrueKey($this->params, 'data')) rsp_error_tips(10001);
        if (!isTrueKey($this->params['data'], 'room') && !isTrueKey($this->params['data'], 'mobile')) rsp_error_tips(10001);
        if (isTrueKey($this->params['data'], 'room')) return $this->params;

        // 设备房产
        $device_project_info = $this->pm->post('/device/v2/lists', ['device_id' => $this->params['device_id']]);
        $device_project_info = ($device_project_info['code'] === 0 && $device_project_info['content']) ? $device_project_info['content'][0] : [];
        if (!$device_project_info) {
            info(__METHOD__, ['error' => '设备项目为空', 'device_id' => $this->params['device_id']]);
            rsp_die_json(10001, '设备项目为空');
        }
        $project_id = $device_project_info['project_id'];


        $space_children = $this->pm->post('/space/children', ['space_id' => $device_project_info['space_id']]);
        $space_children = ($space_children['code'] === 0 && $space_children['content']) ? $space_children['content'] : [];
        $space_children_ids = array_unique(array_filter(array_column($space_children, 'space_id')));

        $post = [
            'space_ids' => $space_children_ids,
        ];
        $house_ids = $this->pm->post('/house/basic/lists', $post);
        $house_ids = ($house_ids['code'] === 0 && $house_ids['content']) ? $house_ids['content'] : [];
        if (!$house_ids) {
            info(__METHOD__, ['error' => '查询设备房产失败', 'device_id' => $this->params['device_id']]);
            rsp_die_json(10001, '查询设备房产失败');
        }
        $house_ids = array_unique(array_filter(array_column($house_ids, 'house_id')));

        // 手机住户
        $mobile = $this->params['data']['mobile'];
        unset($this->params['data']['mobile']);
        $tenement_ids = $this->user->post('/tenement/userlist', ['page' => 1, 'pagesize' => 1, 'mobile' => $mobile, 'project_id' => $project_id]);
        $tenement_ids = ($tenement_ids['code'] === 0 && $tenement_ids['content']) ? $tenement_ids['content']['lists'] : [];
        if (!$tenement_ids) rsp_error_tips(10008, '业主');
        $tenement_ids = array_unique(array_filter(array_column($tenement_ids, 'tenement_id')));

        // 住户房产
        $house_by_tenements = $this->user->post('/house/lists', ['tenement_ids' => $tenement_ids, 'house_ids' => $house_ids]);
        $house_by_tenements = ($house_by_tenements['code'] === 0 && $house_by_tenements['content']) ? $house_by_tenements['content'] : [];
        if (!$house_by_tenements) rsp_die_json(10001, '该手机号没有关联房产，您可以通过房间号呼叫！');

        $house_infos = $this->pm->post('/house/basic/lists', ['house_ids' => array_unique(array_filter(array_column($house_by_tenements, 'house_id')))]);
        $house_infos = ($house_infos['code'] === 0 && $house_infos['content']) ? many_array_column($house_infos['content'], 'house_id') : [];

        // 每个房产对应的住户
        $house_by_ids = $this->user->post('/house/lists', ['house_ids' => array_unique(array_filter(array_column($house_by_tenements, 'house_id')))]);
        $house_by_ids = ($house_by_ids['code'] === 0 && $house_by_ids['content']) ? $house_by_ids['content'] : [];
        $house_by_ids_merged = [];
        foreach ($house_by_ids ?: [] as $item) {
            $house_by_ids_merged[$item['house_id']][] = $item;
        }

        $house_room = '';
        $tenement_identify_tag_id = 0;
        foreach ($house_by_tenements ?: [] as $item) {
            if ($item['tenement_identify_tag_id'] === 917) {
                $tenement_identify_tag_id = 917;
                if ($item['out_time'] > 0 && $item['out_time'] < time()) continue;
                if ($item['tenement_house_status'] === 'N') continue;
                $house_room = getArraysOfvalue($house_infos, $item['house_id'], 'house_room');
                break;
            }

            $tenement_identify_tag_id = 916;
            $house_tenement_identify_tag_ids = array_column($house_by_ids_merged[$item['house_id']], 'tenement_identify_tag_id');
            if (!in_array(917, $house_tenement_identify_tag_ids)) {
                if ($item['out_time'] > 0 && $item['out_time'] < time()) continue;
                if ($item['tenement_house_status'] === 'N') continue;
                $house_room = getArraysOfvalue($house_infos, $item['house_id'], 'house_room');
                break;
            }
            $all_moved_away = true;
            foreach ($house_by_ids_merged[$item['house_id']] ?: [] as $v) {
                if ($v['tenement_identify_tag_id'] === 916) continue;
                if (!($v['out_time'] > 0 && $v['out_time'] < time())) {
                    if ($v['tenement_house_status'] === 'N') continue;
                    $all_moved_away = false;
                    break;
                }
            }
            if ($all_moved_away) {
                if ($item['out_time'] > 0 && $item['out_time'] < time()) continue;
                if ($item['tenement_house_status'] === 'N') continue;
                $house_room = getArraysOfvalue($house_infos, $item['house_id'], 'house_room');
                break;
            }
        }
        if (!$house_room) {
            $msg = $tenement_identify_tag_id === 916 ? '房产处于出租状态，请呼叫当前住户手机号或房号' : '该住户已搬离，请呼叫房号';
            rsp_die_json(10001, $msg);
        }
        $this->params['data']['room'] = $house_room;
        return $this->params;
    }

    // 道闸远程控制下发
    private function filter1312()
    {
        if (!isTrueKey($this->params, 'data') || !isTrueKey($this->params['data'], 'cmd')) rsp_error_tips(10001);
        if (!in_array($this->params['data']['cmd'], ['open', 'close'])) rsp_die_json(10001, 'data.cmd参数错误');
        $space_id = $this->pm->post('/device/v2/lists', ['device_id' => $this->params['device_id']]);
        $space_id = $space_id['content'][0]['space_id'] ?? '';
        if (!$space_id) {
            info(__METHOD__, ['error' => '设备space_id为空', 'device_id' => $this->params['device_id']]);
            rsp_die_json(10001, '设备space_id为空');
        }
        $outer_id = $this->pm->post('/space/show', ['space_id' => $space_id]);
        $outer_id = $outer_id['content']['outer_id'] ?? '';
        if (!$outer_id) {
            info(__METHOD__, ['error' => '空间outer_id未配置', 'space_id' => $space_id]);
            rsp_die_json(10001, '空间outer_id未配置');
        }
        $this->params['data']['pid'] = $outer_id;
        return $this->params;
    }

    // 显示远程控制下发
    private function filter1385()
    {
        if (!isTrueKey($this->params, 'data') || !isTrueKey($this->params['data'], ...['text', 'line', 'duration'])) rsp_error_tips(10001);
        $space_id = $this->pm->post('/device/v2/lists', ['device_id' => $this->params['device_id']]);
        $space_id = $space_id['content'][0]['space_id'] ?? '';
        if (!$space_id) {
            info(__METHOD__, ['error' => '设备space_id为空', 'device_id' => $this->params['device_id']]);
            rsp_die_json(10001, '设备space_id为空');
        }
        $outer_id = $this->pm->post('/space/show', ['space_id' => $space_id]);
        $outer_id = $outer_id['content']['outer_id'] ?? '';
        if (!$outer_id) {
            info(__METHOD__, ['error' => '空间outer_id未配置', 'space_id' => $space_id]);
            rsp_die_json(10001, '空间outer_id未配置');
        }
        $this->params['data']['pid'] = $outer_id;
        return $this->params;
    }

    // 语音远程控制下发
    private function filter1313()
    {
        if (!isTrueKey($this->params, 'data') || !isTrueKey($this->params['data'], 'text')) rsp_error_tips(10001);
        $space_id = $this->pm->post('/device/v2/lists', ['device_id' => $this->params['device_id']]);
        $space_id = $space_id['content'][0]['space_id'] ?? '';
        if (!$space_id) {
            info(__METHOD__, ['error' => '设备space_id为空', 'device_id' => $this->params['device_id']]);
            rsp_die_json(10001, '设备space_id为空');
        }
        $outer_id = $this->pm->post('/space/show', ['space_id' => $space_id]);
        $outer_id = $outer_id['content']['outer_id'] ?? '';
        if (!$outer_id) {
            info(__METHOD__, ['error' => '空间outer_id未配置', 'space_id' => $space_id]);
            rsp_die_json(10001, '空间outer_id未配置');
        }
        $this->params['data']['pid'] = $outer_id;
        return $this->params;
    }

    // 支付通知下发
    private function filter1618()
    {
        if (!isTrueKey($this->params, 'data') || !isTrueKey($this->params['data'], ...['plate', 'platecolor', 'paidmoney', 'appid', 'tnum'])) rsp_error_tips(10001);
        $space_id = $this->pm->post('/device/v2/lists', ['device_id' => $this->params['device_id']]);
        $space_id = $space_id['content'][0]['space_id'] ?? '';
        if (!$space_id) {
            info(__METHOD__, ['error' => '设备space_id为空', 'device_id' => $this->params['device_id']]);
            rsp_die_json(10001, '设备space_id为空');
        }
        $outer_id = $this->pm->post('/space/show', ['space_id' => $space_id]);
        $outer_id = $outer_id['content']['outer_id'] ?? '';
        if (!$outer_id) {
            info(__METHOD__, ['error' => '空间outer_id未配置', 'space_id' => $space_id]);
            rsp_die_json(10001, '空间outer_id未配置');
        }
        $this->params['data']['pid'] = $outer_id;
        return $this->params;
    }

    // 查询停车费用
    private function filter1623()
    {
        if (!isTrueKey($this->params, 'data') || !isTrueKey($this->params['data'], ...['plate', 'platecolor'])) rsp_error_tips(10001);
        $space_id = $this->pm->post('/device/v2/lists', ['device_id' => $this->params['device_id']]);
        $space_id = $space_id['content'][0]['space_id'] ?? '';
        if (!$space_id) {
            info(__METHOD__, ['error' => '设备space_id为空', 'device_id' => $this->params['device_id']]);
            rsp_die_json(10001, '设备space_id为空');
        }
        $outer_id = $this->pm->post('/space/show', ['space_id' => $space_id]);
        $outer_id = $outer_id['content']['outer_id'] ?? '';
        if (!$outer_id) {
            info(__METHOD__, ['error' => '空间outer_id未配置', 'space_id' => $space_id]);
            rsp_die_json(10001, '空间outer_id未配置');
        }
        $this->params['data']['pid'] = $outer_id;
        return $this->params;
    }
}