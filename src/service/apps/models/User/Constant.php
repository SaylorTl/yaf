<?php
/**
 * Created by PhpStorm.
 * User: wuli
 * Date: 2020/9/24
 * Time: 15:07
 */

namespace User;

class ConstantModel
{
    /**
     * 住户信息中车辆信息的key对应下列的key
     * 下列的数组中value对应停车场适配器月卡列表中的key
     */
    const CONTRACT_COLUMNS = [
        'car_brand' => 'car_brand_id',
        'car_brand_name' => 'car_brand',
        'car_model' => 'car_model',
        'car_resource_id' => 'car_resource_id',
        'car_type' => 'car_type_id',
        'car_type_name' => 'car_type',
        'car_type_tag_id' => 'car_type_tag_id',
        'car_type_tag_name' => 'car_type_tag_name',
        'create_at' => 'create_at',
        'driver_id' => 'driver_id',
        'plate' => 'plate',
        'rule' => 'rule_name',
        'space_name' => 'space_name',
        'tenement_id' => 'tenement_id',
        'update_at' => 'update_at',
    ];

}
