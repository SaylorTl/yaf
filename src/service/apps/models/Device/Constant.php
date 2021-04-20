<?php

namespace Device;

class ConstantModel
{
    const RESOURCE_TYPES = [
        'tenement'      => 10013,
        'temp_tenement' => 10034,
        'device_event'  => 10035,
    ];

    const EVENT_RESULT_MAP = [
        '成功'      => 1145,
        '失败'      => 1146,

        'success'   => 1145,
        'fail'      => 1146,
        'failed'    => 1146,

        'SUCCESS'   => 1145,
        'FAIL'      => 1146,
        'FAILED'    => 1146,
    ];

    //用户轨迹事件标签
    const USER_TRAIL_TAG = [
        913,
        919,
        1139
    ];

    /**
     * 短码缓存车牌
     */
    const SHORT_CODE = 'PREOUT:DEVICE_SHORT_CODE';

    /**
     * 记录开闸用户
     */
    const INOUT_CLIENT = "INOUT:OUT_OPERATOR_CLIENT_BY_THROUGH_ID";
}