<?php

class Vendor extends Base {

	public function lists($params = [])
    {
        $lists = $this->device->post('/vendor/lists');
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        $total = $this->device->post('/vendor/count', $params);
        if ($total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => 0, 'lists' => $lists['content']]);

        $data = array_map(function ($m) {
            return [
                'vendor_id' => $m['vendor_id'],
                'vendor_name' => $m['vendor_name'],
            ];
        }, $lists['content']);
        rsp_success_json(['total' => (int)$total['content'], 'lists' => $data]);
    }

    public function add($params = [])
    {
        $fields = [
            'vendor_name',
        ];
        if ($diff_fields = get_empty_fields($fields, $params)) rsp_error_tips(10001, implode('、', $diff_fields));

        $vendor_id = resource_id_generator(self::RESOURCE_TYPES['vendor']);
        if(!$vendor_id) rsp_die_json(10003,'生成vendor_id失败');

        $result = $this->device->post('/vendor/add', ['vendor_id' => $vendor_id, 'vendor_name' => $params['vendor_name']]);
        if ($result['code'] !== 0) rsp_die_json(10005, $result['message']);
        rsp_success_json($vendor_id);
    }

}

