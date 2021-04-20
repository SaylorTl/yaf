<?php

class Search extends Base
{

    public function query($params = [])
    {

        if (!is_not_empty($params, 'keyword')) {
            rsp_die_json(10001, '请输入查询关键字');
        }

        if (!$this->employee_id) {
            rsp_die_json(10002, '用户信息缺失');
        }

        $params['keyword'] = replaceSpecialChar($params['keyword']);

        if (!is_not_empty($params, 'keyword')) {
            rsp_die_json(10001, '未找到相关数据，请重新输入关键字~');
        }

        $info = ['keyword' => trim($params['keyword']), 'session_info' => $_SESSION];

        $redis = Comm_Redis::getInstance();
        $redis->select(8);

        $redis_key = 'OAS_' . $this->employee_id . '_' . $_SESSION['oauth_app_id'] . '_' . md5($params['keyword']);
        $redis->del($redis_key);

        $redis->lpush(self::LIST_KEY, json_encode($info));

        rsp_success_json(1);

    }

    public function data($params = [])
    {
        ini_set('memory_limit', '256M');

        if (!$this->employee_id) {
            rsp_die_json(10002, '用户信息缺失');
        }

        if (!is_not_empty($params, 'keyword')) {
            rsp_die_json(10001, '缺少关键字');
        }

        $redis_key = 'OAS_' . $this->employee_id . '_' . $_SESSION['oauth_app_id'] . '_' . md5($params['keyword']);
        $redis = Comm_Redis::getInstance();
        $redis->select(8);

        $exists = $redis->exists($redis_key);
        if(!$exists){
            rsp_success_json(['lists' => [], 'count' => 0], '查询中...');
        }

        $data = $redis->get($redis_key);
        $data =  $data ? json_decode($data,true) : [];
        $count = count($data);

        if (empty($data)) {
            rsp_die_json(10002, '未找到相关数据，请重新输入关键字~');
        }

        if (isTrueKey($params, 'page', 'pagesize') && !empty($data)) {
            $start = ((int)$params['page'] - 1) * (int)$params['pagesize'];
            $data = array_slice($data, $start, (int)$params['pagesize']);
        }

        rsp_success_json(['lists' => $data, 'count' => $count], 'success');
    }

}