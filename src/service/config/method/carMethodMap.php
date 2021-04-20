<?php

return [
    'admin.car.brands' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'car/common/brand_lists'],
    'admin.car.types' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'car/common/type_lists'],

    // 无牌车扫码进场
    'app.car.unlicensed.parking' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'car/unlicensed/parking'],
    'app.car.unlicensed.lists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'car/unlicensed/lists'],
];
