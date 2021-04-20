<?php

return [
    'admin.employee.list' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/employee/EmployeeList'],
    'admin.employee.add' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/employee/EmployeeAdd'],
    'admin.employee.extlist' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/employee/Employeeextlist'],
    'admin.employee.update' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/employee/EmployeeUpdate'],
    'admin.employee.search' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/employee/EmployeeSearch'],

    'admin.tenement.tenementList' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/tenement/TenementList'],
    'admin.tenement.tenementAdd' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/tenement/TenementAdd'],
    'admin.tenement.extlist' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/tenement/Tenementextlist'],
    'admin.tenement.update' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/tenement/TenementUpdate'],
    'admin.tenement.userHouseLists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/tenement/user_house_lists'],

    'admin.visitor.visitorList' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/visitor/VisitorList'],
    'admin.visitor.visitorAdd' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/visitor/VisitorAdd'],
    'admin.visitor.extlist' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/visitor/Visitorextlist'],
    'admin.visitor.update' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/visitor/VisitorUpdate'],

    'admin.user.visitorApplyGene' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/visitorapply/visitorApplyAdd'],
    'admin.user.visitorApplyLists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/visitorapply/visitorApplyLists'],
    'admin.user.visitorDeviceLists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/visitorapply/visitorDeviceLists'],
    'admin.user.visitorApplyCheck' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/visitorapply/visitorApplyCheck'],

    //排班管理
    'admin.schedule.typeList' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/schedule/typeList'],
    'admin.schedule.typeAdd' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/schedule/typeAdd'],
    'admin.schedule.typeUpdate' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/schedule/typeUpdate'],
    'admin.schedule.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/schedule/lists'],
    'admin.schedule.save' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/schedule/save'],
    'admin.schedule.export' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/schedule/export'],
    'admin.schedule.v2_lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'user/schedule/v2_lists'],



    'app.client.houseList' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/client/houseList'],
    'app.client.houseAdd' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/client/houseAdd'],
    'app.client.houseDel' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/client/houseDel'],
    'app.client.houseUpdate' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/client/houseUpdate'],

    //app端住户管理
    'app.tenement.userHouseLists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/tenement/user_house_lists'],
    'app.tenement.tenementList' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/tenement/TenementList'],
    'app.tenement.tenementAdd' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/tenement/TenementAdd'],
    'app.tenement.extlist' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/tenement/Tenementextlist'],
    'app.tenement.update' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/tenement/TenementUpdate'],
    'app.tenement.tenementShow' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/tenement/TenementShow'],
    'app.tenement.houseList' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/tenement/tenement_house_lists'],
    'app.tenement.house' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/tenement/house_lists'],
    'app.tenement.projectList' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/tenement/tenement_project_lists'],


    'app.user.register' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/usercenter/register'],
    'app.user.smsCaptcha' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/usercenter/smsCaptcha'],
    'app.user.userDevice' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/usercenter/userDevice'],

    'app.user.visitorApplyGene' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/visitorapply/visitorApplyAdd'],
    'app.user.visitorApplyLists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/visitorapply/visitorApplyLists'],
    'app.user.visitorDeviceLists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/visitorapply/visitorDeviceLists'],
    'app.user.visitorApplyCheck' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/visitorapply/visitorApplyCheck'],

    'app.visitor.visitorAdd' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/visitor/VisitorAdd'],
    'app.visitor.visitorList' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/visitor/visitorList'],
    'app.visitor.visitorShow' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/visitor/visitorShow'],
    'app.visitor.deviceAuthShow' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/visitor/deviceAuthShow'],
    'app.keycard.infoAdd' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/keycard/infoAdd'],

    'app.user.invoice.show' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/invoice/show'],
    'app.user.invoice.check' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/invoice/check'],
    'app.user.invoice.apply' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'user/invoice/apply'],

    //pos端接口
    'pos.tenement.tenementList' => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'user/tenement/TenementList'],
    'pos.tenement.tenementAdd' => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'user/tenement/TenementAdd'],
    'pos.tenement.extlist' => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'user/tenement/Tenementextlist'],
    'pos.tenement.update' => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'user/tenement/TenementUpdate'],
    'pos.tenement.tenementShow' => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'user/tenement/TenementShow'],
    'pos.tenement.houseList' => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'user/tenement/tenement_house_lists'],
    'pos.tenement.house' => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'user/tenement/house_lists'],
];


