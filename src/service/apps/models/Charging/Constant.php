<?php
/**
 * Created by PhpStorm.
 * User: wuli
 * Date: 2020/12/30
 * Time: 10:07
 */

namespace Charging;

class ConstantModel
{
    /**
     * 计费类型
     * 0 所有 1、停车费-月卡  2、停车费-临停
     * order_type 订单类型tag值 697：停车场
     */
    const CHARGE_METHODS = [
        'Zhsq' => [
            1 => ['order_type' => 697, 'name' => '月卡缴费', 'class' => '\Charging\App\Zhsq\MonthModel'],
            2 => ['order_type' => 697, 'name' => '临停缴费', 'class' => '\Charging\App\Zhsq\TempModel'],
        ]
    ];

    /**
     * 平台类型
     */
    const PLATFORM_TYPE = [
        1525 => 'Zhsq',
        1526 => 'EP'
    ];

    /**
     * 计费订单类型
     * order_type 订单类型tag值 697：停车场
     */
    const CHARGE_ORDER_TYPES = [697];

    /**
     * 0元计费 缓存信息key
     */
    const ZERO_REDIS = "M:ZERO_CHARGE";

    /**
     * 计费缓存时间
     */
    const CHARGE_TIMES = 70;

}