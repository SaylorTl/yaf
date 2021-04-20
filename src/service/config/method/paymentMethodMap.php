<?php

return [
    'app.payment.unified' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/payment/unified'],
    'app.payment.payOrder' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/payment/payOrder'],
    'app.payment.cardPayOrder' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/payment/cardPayOrder'],

    'app.order.getQrCode' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/getQrCode'],
    'app.order.getQrInfo' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/getQrInfo'],
    'app.order.getQrStatus' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/getQrStatus'],

    'app.order.lists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/lists'],
    'app.order.show' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/show'],
    'app.order.getOrderStatus' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/getOrderStatus'],

    'app.order.posSubLists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/posSubLists'],
    'app.order.posLists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/posLists'],
    'app.order.posShow' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/posShow'],
    'app.order.posOrderSublists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/posOrderSublists'],
    'app.order.posOrderSubshow' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/posOrderSubshow'],
    'app.payment.conf.lists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/conf/lists'],
    'app.order.printOrder' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/printOrder'],
    'app.order.tradeSourceLists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/tradeSourceLists'],
    'app.order.geneOrderNum' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/geneOrderNum'],

    'app.order.orderBind' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/orderBind'],

    'app.order.updateOrderStatus' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/updateOrderStatus'],

    'app.order.userBind' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'payment/order/userBind'],
];