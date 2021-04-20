<?php

class Vendor extends Base {

	public function lists($params = [])
    {
        $lists = $this->pm->post('/project/vendor/lists');
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        $total = $this->pm->post('/project/vendor/count', $params);
        if ($total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => 0, 'lists' => $lists['content']]);

        $data = array_map(function ($m) {
            return [
                'vendor_id' => $m['vendor_id'],
                'vendor_name' => $m['vendor_name'],
            ];
        }, $lists['content']);
        rsp_success_json(['total' => (int)$total['content'], 'lists' => $data]);
    }
}

