<?php

return [
    //EP订单业务通知回调
    'admin.report.project.basicInfo' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'report/project/basicInfo'],
    'admin.report.businessOrder.overview' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'report/businessOrder/overview'],
    'admin.report.businessOrder.report' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'report/businessOrder/report'],
    'admin.report.house.housingStatus' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'report/house/housingStatus'],
    
    'admin.report.personnelInOut.perHour' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'report/personnelinout/perHour'],
    'admin.report.personnelInOut.overview' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'report/personnelinout/overview'],
    
    'admin.report.carInOut.perHour' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'report/carinout/perHour'],
    'admin.report.carInOut.overview' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'report/carinout/overview'],

];
