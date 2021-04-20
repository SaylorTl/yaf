<?php

class Type extends Base
{

    /**
     * 获取资源类型列表
     */
    public function lists()
    {
        $result = $this->resource->post('/type/lists');
        if ($result['code'] != 0) {
            rsp_die_json(10001, '获取资源类型错误');
        }

        rsp_success_json($result['content'], 'success');
    }


    /**
     * @param array $params
     * 获取资源类型列表
     */
    public function info($params = [])
    {
        $result = $this->resource->post('/type/show', $params);
        if ($result['code'] != 0) {
            rsp_die_json(10001, '获取资源类型错误');
        }

        rsp_success_json($result['content'], 'success');
    }
}