<?php

class Resource extends Base
{

    public function lite($params = [])
    {
        if (!isTrueKey($params, 'resource_lite')) rsp_error_tips(10001);
        $resource_id = $this->resource->post('/resource/id/lite', ['resource_lite' => $params['resource_lite']]);
        $resource_id = $resource_id['content'] ?? '';
        if (!$resource_id) rsp_success_json([]);

        $type_id = substr($resource_id, -5);
        $type = $this->resource->post('/type/show', ['type_id' => $type_id]);
        $type = $type['content'] ?? [];
        rsp_success_json([
            'resource_id' => $resource_id,
            'resource_lite' => $params['resource_lite'],
            'type_id' => $type['type_id'] ?? '',
            'type_name' => $type['type_name'] ?? '',
            'type_cname' => $type['type_cname'] ?? '',
        ]);
    }
}