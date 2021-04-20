<?php

use Wechat\ConstantModel as Constant;

final class Handle extends Base
{
    public function setting($query)
    {
        if (false == isTrueKey($query, 'handle', 'key')) {
            rsp_die_json(10001, "参数缺失");
        }
        if (!in_array($query['key'], array_values(Constant::REDIS_KEY))) {
            rsp_die_json(10001, "无权设置此操作");
        }
        if (!method_exists($this, $query['handle'])) {
            rsp_die_json(10001, "操作类型信息错误");
        }

        $method = $query['handle'];
        $map_data = $query['map_data'] ?? [];
        if ($map_data && is_array($map_data)) {
            array_map(function ($m) use ($method) {
                $data = $m['data'] ?? '';
                $data = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
                $secondKey = $m['second_key'] ?? '';
                if (isTrueKey($m, 'key')) {
                    $this->$method($m['key'], $secondKey, $data);
                }
            }, $map_data);
            rsp_success_json('', '操作成功');
        }

        $data = $query['data'] ?? '';
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        $secondKey = $query['second_key'] ?? '';
        $result = $this->$method($query['key'], $secondKey, $data);
        info(__METHOD__, ['result' => $result, 'params' => $query]);
        rsp_success_json($result, '操作成功');
    }
}