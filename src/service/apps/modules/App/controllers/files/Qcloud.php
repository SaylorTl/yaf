<?php

class Qcloud extends Base
{
    /**
     * @param array $params
     * 获取临时腾讯Cos临时凭证（token）以及上传路径（url）
     */
    public function token($params = [])
    {
        if (!isTrueKey($params, 'bucket')) {
            rsp_die_json(10001, 'bucket缺失');
        }

        if (!isTrueKey($params, 'region')) {
            rsp_die_json(10001, 'region缺失');
        }

        if (!isTrueKey($params, 'resource_type')) {
            rsp_die_json(10001, '资源类型缺失');
        }

        // 检查resource_type是否合法
        $typeRes = $this->resource->post('/type/show', ['type_name' => $params['resource_type']]);
        if ($typeRes['code'] != 0 || empty($typeRes['content'])) {
            rsp_die_json(10001, '资源类型错误');
        }

        $args = [
            'bucket' => $params['bucket'],
            'region' => $params['region'],
            'resource_type' => $params['resource_type'],
        ];
        // 获取腾讯Cos临时凭证
        $result = $this->file->post('/cos/token', $args);
        if ($result['code'] !== 0) {
            rsp_die_json(10001, $result['message']);
        }

        rsp_success_json($result['content']);
    }



}