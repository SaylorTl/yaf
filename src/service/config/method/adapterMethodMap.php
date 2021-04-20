<?php

return [
    //费用详情查询
    'app.adapter.charge.detail' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/charge/detail'],
    //用户点击详情限制
    'app.adapter.detail.limit' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/charge/detail_limit'],

    //查询车辆信息
    'app.adapter.car.lists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/news/car_lists'],

    //查询月卡信息
    'app.adapter.contract.lists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/news/contract'],

    //查询月卡费用
    'app.adapter.contract.cost' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/news/contract_cost'],

    //查询用户房间信息
    'app.adapter.room.lists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/news/room'],

    //pos端接口
    'pos.adapter.charge.detail' => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'adapter/charge/detail'],

    //打印电子收据
    'app.adapter.print.receipt' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/charge/print_receipt'],

    //是否支持收据打印
    'app.adapter.support.receipt' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/charge/support_receipt'],

    //提供给测试同学使用
    'app.adapter.arrears.test' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/charge/arrears_test'],

    //销单
    'app.adapter.cancel.order' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/notification/notification_service_platform'],

    //获取预进场车牌
    'app.adapter.station.devices.getPlate' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/stationdevices/getPlate'],

    //停车场道闸开闸
    'app.adapter.station.devices.open' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/stationdevices/open'],

    //发送邮件
    'app.adapter.charge.sendMail' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/charge/send_mail'],

    //转换图片
    'app.adapter.charge.convertImg' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/charge/convert_img'],

    //转换图片
    'app.adapter.charge.historyEmail' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/charge/history_email'],

    //月卡计费
//    'app.adapter.station.contractFee' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/stationcharge/contractFee'],

    //月卡列表
//    'app.adapter.station.contractLists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/stationcharge/contractLists'],

    //EP订单业务通知回调
    'app.adapter.eporder.notify' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/notification/epstation'],

    //新版计费
    'app.adapter.cloud.fee' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'adapter/charge/new_cost'],

];
