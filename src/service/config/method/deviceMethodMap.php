<?php

return [
    'admin.device.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'device/device/lists'],
    'admin.device.add' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'device/device/add'],
    'admin.device.update' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'device/device/update'],
    
    'admin.device.template.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'device/devicetemplate/lists'],
    'admin.device.template.show' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'device/devicetemplate/show'],
    'admin.device.template.add' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'device/devicetemplate/add'],
    'admin.device.template.update' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'device/devicetemplate/update'],

    'admin.device.vendor.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'device/vendor/lists'],
    'admin.device.vendor.add' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'device/vendor/add'],

    'admin.device.tenement.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'device/devicetenement/lists'],

    'admin.device.employee.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'device/deviceemployee/lists'],
    'admin.device.employee.toggle' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'device/deviceemployee/toggle'],


    'device.message.send' => ['module' => 'device', 'controller' => 'dispatch', 'action' => 'device/message/send'],
    'device.calling' => ['module' => 'device', 'controller' => 'dispatch', 'action' => 'device/message/calling'],

    'device.event.lists' => ['module' => 'device', 'controller' => 'dispatch', 'action' => 'device/event/lists'],
    'device.event.update' => ['module' => 'device', 'controller' => 'dispatch', 'action' => 'device/event/update'],
    'device.event.images' => ['module' => 'device', 'controller' => 'dispatch', 'action' => 'device/event/images'],
    'device.event.add' => ['module' => 'device', 'controller' => 'dispatch', 'action' => 'device/event/add'],

    'device.video.ys.token' => ['module' => 'device', 'controller' => 'dispatch', 'action' => 'video/ys/token'],

    'device.device.show' => ['module' => 'device', 'controller' => 'dispatch', 'action' => 'device/device/show'],
    'app.resource.lite' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'resource/resource/lite'],
    
    'app.device.add' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'device/device/add'],
    'app.device.getDeviceIds' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'device/device/getDeviceIds'],
    'app.device.template.lists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'device/devicetemplate/lists'],

    'device.inout.lists' => ['module' => 'device', 'controller' => 'dispatch', 'action' => 'device/inout/lists'],
    'device.inout.lists.detail' => ['module' => 'device', 'controller' => 'dispatch', 'action' => 'device/inout/lists_detail'],
    'device.inout.short.code' => ['module' => 'device', 'controller' => 'dispatch', 'action' => 'device/inout/short_code'],
    'device.inout.zero.opening' => ['module' => 'device', 'controller' => 'dispatch', 'action' => 'device/inout/temp_zero_opening'],
    'device.in.open' => ['module' => 'device', 'controller' => 'dispatch', 'action' => 'device/inout/inOpen'],
];