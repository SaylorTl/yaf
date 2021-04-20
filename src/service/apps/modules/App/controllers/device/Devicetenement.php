<?php

class Devicetenement extends Base
{
    
    
    public function lists($params = [])
    {
        if (!isTrueKey($params, ...['page', 'pagesize', 'device_id'])) {
            rsp_error_tips(10001, 'page pagesize device_id');
        }
        
        $device = $this->pm->post('/device/v2/lists',
            ['page' => 1, 'pagesize' => 1, 'device_id' => $params['device_id']]);
        $device = ($device['code'] === 0 && $device['content']) ? $device['content'][0] : [];
        if (!$device) {
            rsp_error_tips(10002, '设备不存在');
        }
        
        // house_ids
        $post = [
            'project_id' => $device['project_id'],
        ];
        if ($device['space_id']) {
            $spaces = $this->pm->post('/space/children', ['space_id' => $device['space_id']]);
            $spaces = ($spaces['code'] === 0 && $spaces['content']) ? $spaces['content'] : [];
            if (!$spaces) rsp_success_json(['total' => 0, 'lists' => []]);
            $post['space_ids'] = array_unique(array_filter(array_column($spaces, 'space_id')));
        }
        $house_ids = $this->pm->post('/house/basic/lists', $post);
        $house_ids = ($house_ids['code'] === 0 && $house_ids['content']) ? $house_ids['content'] : [];
        if (!$house_ids) {
            rsp_success_json(['total' => 0, 'lists' => []]);
        }
        $house_ids = array_unique(array_filter(array_column($house_ids, 'house_id')));
        
        // tenement_ids
        $tenement_ids = $post = [];
        if (isset($params['real_name']) && trim($params['real_name'])) {
            $post['real_name'] = $params['real_name'];
        }
        if (isset($params['mobile']) && trim($params['mobile'])) {
            $post['mobile'] = $params['mobile'];
        }
        if ($post) {
            $tenement_ids = $this->user->post('/tenement/userlist',
                array_merge($post, ['page' => 1, 'pagesize' => 99999]));
            $tenement_ids = ($tenement_ids['code'] === 0 && $tenement_ids['content']) ? $tenement_ids['content']['lists'] : [];
            if (!$tenement_ids) {
                rsp_success_json(['total' => 0, 'lists' => []]);
            }
            $tenement_ids = array_unique(array_filter(array_column($tenement_ids, 'tenement_id')));
        }
        
        // list
        $post = [
            'page' => $params['page'],
            'pagesize' => $params['pagesize'],
            'all_house_ids' => $house_ids,
        ];
        if (isTrueKey($params, 'tenement_status_tag_id')) {
            switch ((int)$params['tenement_status_tag_id']) {
                case self::TENEMENT_STATUS['使用中']:
                    $post['out_time_service'] = date('Y-m-d H:i:s');
                    break;
                case self::TENEMENT_STATUS['已搬离']:
                    $post['out_time_begin'] = date('Y-m-d H:i:s', 0);
                    $post['out_time_end'] = date('Y-m-d H:i:s');
                    break;
                default:
                    rsp_success_json(['total' => 0, 'lists' => []]);
                    break;
            }
        }
        if (isTrueKey($params, 'tenement_identify_tag_id')) {
            $post['tenement_identify_tag_id'] = $params['tenement_identify_tag_id'];
        }
        if ($tenement_ids) {
            $post['all_tenement_ids'] = $tenement_ids;
        }
        $lists = $this->user->post('/house/lists', $post);
        if ($lists['code'] !== 0 || !$lists['content']) {
            rsp_success_json(['total' => 0, 'lists' => []]);
        }
        
        //total
        unset($post['page'], $post['pagesize']);
        $total = $this->user->post('/house/count', $post);
        $total = ($total['code'] === 0 && $total['content']) ? $total['content'] : 0;
        
        // user info
        $tenement_ids = array_unique(array_filter(array_column($lists['content'], 'tenement_id')));
        $house_ids = array_unique(array_filter(array_column($lists['content'], 'house_id')));
        $tenements = $this->user->post('/tenement/userlist',
            ['tenement_ids' => $tenement_ids, 'house_ids' => $house_ids, 'page' => 1, 'pagesize' => 99999]);
        $tenements = ($tenements['code'] === 0 && $tenements['content']) ? many_array_column($tenements['content']['lists'],
            'tenement_id') : [];
        
        // house info
        $house_ids = array_unique(array_filter(array_column($lists['content'], 'house_id')));
        $houses = $this->pm->post('/house/lists', ['house_ids' => $house_ids]);
        $houses = ($houses['code'] === 0 && $houses['content']) ? many_array_column($houses['content'],
            'house_id') : [];
        
        $result = array_map(function ($m) use ($tenements, $houses) {
            $tenement = $tenements[$m['tenement_id']] ?? [];
            $m = array_merge($tenement, $m);
            $m['tenement_status_tag_id'] = (!$m['out_time'] || $m['out_time'] > time()) ? self::TENEMENT_STATUS['使用中'] : self::TENEMENT_STATUS['已搬离'];
            
            $house_properties = getArraysOfvalue($houses, $m['house_id'], 'house_property');
            $m['owner_name'] = $m['owner_mobile'] = '';
            foreach ($house_properties ?: [] as $item) {
                if ($item['proprietor_type'] === 'owner') {
                    $m['owner_name'] = $item['proprietor_name'];
                    $m['owner_mobile'] = $item['proprietor_mobile'];
                    break;
                }
            }
            return $m;
        }, $lists['content']);
        
        rsp_success_json(['total' => $total, 'lists' => $result]);
    }
}

