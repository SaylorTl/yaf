<?php

class StationDevices extends Base
{
    /**
     * 获取预进场信息能力标签ID
     */
    const GET_PLATE_ABILITY_ID = 1125;
    
    /**
     * 开闸能力标签ID
     */
    const OPEN_ABILITY_ID = 1126;
    
    /**
     * 获取预进场信息（车牌）接口
     * @param  array  $params
     */
    public function getPlate($params = [])
    {
        set_log(['params' => $params]);
        log_message('----StationDevices/'.__FUNCTION__.'----'.json_encode($params));
        if (!isTrueKey($params, 'device_id')) {
            rsp_die_json(10001, 'device_id参数缺失');
        }
        //检查设备信息
        $device_check_info = $this->checkDevice($params['device_id']);
        if ($device_check_info['code'] != 0) {
            rsp_die_json($device_check_info['code'], $device_check_info['content']);
        }
        $device_info = $device_check_info['content'];
        //检查设备能力
        $ability_tag_ids = array_column($device_info['device_ability_tag_ids'], 'device_ability_tag_id');
        $ability_check_info = $this->checkDeviceAbility($ability_tag_ids, self::GET_PLATE_ABILITY_ID);
        if ($ability_check_info['code'] != 0) {
            rsp_die_json($ability_check_info['code'], $ability_check_info['content']);
        }
        //查询预进场信息
        $get_plate_query = $this->station_adapter->post('/v1/exec', [
            'cmd' => self::GET_PLATE_ABILITY_ID,
            'deviceSn' => $device_info['device_extcode'],
            'data' => ['a'] //空数组传不过去？
        ]);
        if (!isset($get_plate_query) || $get_plate_query['code'] != 0) {
            set_log(['get_plate_query' => $get_plate_query]);
            rsp_die_json(10002, '获取预进场信息失败');
        }
        $plate = $get_plate_query['content']['plate'] ?? '';
        rsp_success_json(['plate' => $plate]);
    }
    
    /**
     * 道闸开闸接口
     * @param  array  $params
     */
    public function open($params = [])
    {
        set_log(['params' => $params]);
        log_message('----StationDevices/'.__FUNCTION__.'----'.json_encode($params));
        if (!isTrueKey($params, 'device_id', 'mobile', 'username') || !isset($params['plate'])) {
            rsp_die_json(10001, 'device_id、mobile、username或plate参数缺失');
        }
        if (!isMobile($params['mobile'])) {
            rsp_die_json(10001, '手机号码非法');
        }
        //检查设备信息
        $device_check_info = $this->checkDevice($params['device_id']);
        if ($device_check_info['code'] != 0) {
            rsp_die_json($device_check_info['code'], $device_check_info['content']);
        }
        $device_info = $device_check_info['content'];
        //检查设备能力
        $ability_tag_ids = array_column($device_info['device_ability_tag_ids'], 'device_ability_tag_id');
        $ability_check_info = $this->checkDeviceAbility($ability_tag_ids, self::OPEN_ABILITY_ID);
        if ($ability_check_info['code'] != 0) {
            rsp_die_json($ability_check_info['code'], $ability_check_info['content']);
        }
        //开闸
        $open_result = $this->station_adapter->post('/v1/exec', [
            'cmd' => self::OPEN_ABILITY_ID,
            'deviceSn' => $device_info['device_extcode'],
            'data' => [
                'mobile' => $params['mobile'],
                'plate' => $params['plate'],
                'title' => '社区云到访登记',
                'direction' => 'in',
                'source' => 'sqy_visit',
                'reason' => 'visit',
                'username' => $params['username']
            ]
        ]);
        if (!isset($open_result) || $open_result['code'] != 0) {
            set_log(['open_result' => $open_result]);
            rsp_die_json(10002, '开闸失败');
        }
        rsp_success_json([]);
    }
    
    /**
     * 检查设备信息：是否合法（存在）、是否有设备编码、是否有关联能力
     * @param  string  $device_id
     * @return array
     */
    private function checkDevice($device_id)
    {
        $device_query = $this->device->post('/device/lists', ['device_id' => $device_id]);
        if (!isset($device_query['code']) || $device_query['code'] != 0) {
            set_log(['device_query' => $device_query]);
            log_message('----StationDevices/'.__FUNCTION__.'----error：'.json_encode($device_query));
            return ['code' => 10002, 'content' => '设备信息查询失败'];
        } elseif (empty($device_query['content'])) {
            return ['code' => 10008, 'content' => '非法的设备ID'];
        }
        $device_info = array_pop($device_query['content']);
        if (empty($device_info['device_extcode'])) {
            return ['code' => 90002, 'content' => '设备编码（device_extcode）数据缺失'];
        } elseif (empty($device_info['device_ability_tag_ids'])) {
            return ['code' => 90002, 'content' => '当前设备未关联任何能力'];
        }
        return ['code' => 0, 'content' => $device_info];
    }
    
    /**
     * 检查设备的能力是否含有目标能力
     * @param  array  $ability_tag_ids  设备包含的能力标签ID
     * @param  int  $ability_target_tag_id  目标能力标签ID
     * @return array
     */
    private function checkDeviceAbility($ability_tag_ids, $ability_target_tag_id)
    {
        if (!in_array($ability_target_tag_id, $ability_tag_ids)) {
            $tag_query = $this->tag->post('/tag/show', ['tag_id' => $ability_target_tag_id]);
            if ($tag_query['code'] == 0 && !empty($tag_query['content'])) {
                $tag_name = $tag_query['content']['tag_name'];
            } else {
                set_log(['tag_query' => $tag_query]);
                log_message('----StationDevices/'.__FUNCTION__.'----error：'.json_encode($tag_query));
                $tag_name = '未知能力';
            }
            return ['code' => 10008, 'content' => '当前设备未关联「'.$tag_name.'」能力'];
        }
        return ['code' => 0, 'content' => ''];
    }
}