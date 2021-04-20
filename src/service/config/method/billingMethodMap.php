<?php

return [
    'admin.billing.type.add' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/type/add'],
    'admin.billing.type.update' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/type/update'],
    'admin.billing.type.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/type/lists'],
    'admin.billing.type.treeLists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/type/tree'],
    'admin.billing.type.show' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/type/show'],

    'admin.billing.account.add' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/account/add'],
    'admin.billing.account.update' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/account/update'],
    'admin.billing.account.treeLists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/account/tree'],
    'admin.billing.account.show' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/account/show'],

    'admin.billing.businessConfig.add' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/BusinessConfig/add'],
    'admin.billing.businessConfig.update' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/BusinessConfig/update'],
    'admin.billing.businessConfig.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/BusinessConfig/lists'],
    'admin.billing.businessConfig.show' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/BusinessConfig/show'],
    'admin.billing.businessConfig.getRule' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/BusinessConfig/getRule'],

    'admin.billing.receivable.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/receivable/lists'],
    'admin.billing.receivable.exceptionCancel' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/receivable/exceptionCancel'],
    'admin.billing.receivable.add' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/receivable/add'],
    'admin.billing.receivable.charge' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/receivable/charge'],
    'admin.billing.receivable.getPenaltyTime' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/receivable/getPenaltyTime'],
    'admin.billing.receivable.tempUpdate' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'billing/receivable/tempUpdate'],
];