<?php

class Files extends Base
{

    /**
     * @param array $params
     * 存储文件属性并获取资源id
     */
    public function bind($params = [])
    {
        if (!isTrueKey($params, 'file_name')) {
            rsp_die_json(10001, 'Path缺失');
        }

        if (!isTrueKey($params, 'attributes')) {
            rsp_die_json(10001, '文件属性缺失');
        }

        if (!isTrueKey($params, 'resource_type')) {
            rsp_die_json(10001, '资源类型缺失');
        }

        $file_name = $params['file_name'];
        $attributes = $params['attributes'];
        $resource_type = $params['resource_type'];

        // 获取资源id
        $resourceRes = $this->resource->post('/resource/id/generator', ['type_name' => $resource_type]);
        if ($resourceRes['code'] != 0) {
            rsp_die_json(10001, '资源id创建失败:' . $resourceRes['message']);
        }
        $resource_id = $resourceRes['content'];
        $args = [
            'file_id' => $resource_id,
            'file_name' => $file_name,
            'file_attributes' => json_encode($attributes),
        ];

        $filesRes = $this->file->post('/bind', $args);
        if ($filesRes['code'] != 0) {
            rsp_die_json(10001, '资源创建失败');
        }

        $result = [
            'resource_id' => (string)$resource_id,
        ];
        rsp_success_json($result, 'success');
    }


    /**
     * @param array $params
     * 获取文件信息
     */
    public function info($params = [])
    {
        if (!isTrueKey($params, 'resource_id')) {
            rsp_die_json(10001, '资源id缺失');
        }

        $file_id = $params['resource_id'];
        $result = $this->file->post('/info', ['file_id' => $file_id]);
        if ($result['code'] != 0 || empty($result['content'])) {
            rsp_die_json(10001, '文件不存在');
        }

        rsp_success_json($result['content']);
    }

}