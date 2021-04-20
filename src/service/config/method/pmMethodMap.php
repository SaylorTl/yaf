<?php

return [
    'admin.pm.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/project/lists'],
    'admin.pm.projects' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/project/projects'],
    'admin.pm.show' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/project/show'],
    'admin.pm.add' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/project/add'],
    'admin.pm.update' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/project/update'],
    'admin.pm.relevance' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/project/relevanceDetails'],

    //架构管理
    'admin.frame.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/frame/lists'],
    'admin.frame.delete' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/frame/delete'],
    'admin.frame.add' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/frame/add'],
    'admin.frame.update' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/frame/update'],
    'admin.frame.basic.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/frame/basic_lists'],
    'admin.frame.project.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/frame/project_lists'],

    //车位管理
    'admin.place.lists'     => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/parkplace/lists'],
    'admin.place.add'       => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/parkplace/add'],
    'admin.place.update'    => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/parkplace/update'],

    //空间管理
    'admin.space.lists'     => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/space/lists'],
    'admin.space.add'       => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/space/add'],
    'admin.space.update'    => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/space/update'],
    'admin.space.tree'    => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/space/tree'],
    'admin.space.names'    => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/space/names'],
    'app.space.lists'    => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/space/lists'],
    'app.space.buildings'     => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/space/buildings'],
    'app.space.houses'    => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/space/houses'],
    'admin.space.types'    => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/space/types'],

    //房产管理
    'admin.pm.house.lists'              => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/house/lists'],
    'admin.pm.house.count'              => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/house/count'],
    'admin.pm.house.add'                => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/house/add'],
    'admin.pm.house.update'             => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/house/update'],
    'admin.pm.house.record.lists'       => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/house/record_lists'],
    'admin.pm.house.basic.lists'        => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/house/basic_lists'],
    'admin.pm.house.cells.lists'        => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/house/cells_lists'],
    'admin.pm.house.property.detail'        => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/house/property_detail'],

    //维修管理
    'admin.repair.lists'     => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/repair/lists'],
    'admin.repair.add'       => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/repair/add'],
    'admin.repair.update'    => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/repair/update'],

    //抄表管理
    'admin.readmeter.lists'     => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/readmeter/lists'],
    'admin.readmeter.add'       => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/readmeter/add'],
    'admin.readmeter.update'    => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/readmeter/update'],

    //房屋中介
    'admin.mediation.lists'     => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/mediation/lists'],
    'admin.mediation.add'       => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/mediation/add'],
    'admin.mediation.update'    => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/mediation/update'],

    //设施管理
    'admin.facility.lists'     => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/facility/lists'],
    'admin.facility.add'       => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/facility/add'],
    'admin.facility.update'    => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/facility/update'],

    //场地租赁
    'admin.yardrent.lists'     => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/yardrent/lists'],
    'admin.yardrent.add'       => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/yardrent/add'],
    'admin.yardrent.update'    => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/yardrent/update'],

    //绿植管理
    'admin.plants.lists'     => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/plants/lists'],
    'admin.plants.add'       => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/plants/add'],
    'admin.plants.update'    => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/plants/update'],

    // 商户
    'admin.pm.mch.add'     => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/mch/add'],

    //app端接口
    'app.pm.lists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/project/lists'],
    'app.pm.projects' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/project/projects'],
    'app.pm.show' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/project/show'],

    'app.pm.house.basic.lists'        => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/house/basic_lists'],
    'app.pm.house.cells.lists'        => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/house/cells_lists'],
    'app.pm.house.basic.tree'        => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/house/basic_tree'],
    'app.pm.house.property.detail'   => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/house/property_detail'],
    'app.pm.house.update'             => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/house/update'],

    'app.device.lists'     => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/device/lists'],
    'app.pm.device.v2.show'  => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/device/device_v2_show'],

//    'app.pm.getProject' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/project/get_project'],
//    'app.pm.setProject' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/project/set_project'],

    'app.pm.house.isUpdated' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/house/is_updated'],
    'app.pm.house.setUpdated' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'pm/house/set_updated'],

    //pos端接口
    'pos.pm.lists' => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'pm/project/lists'],
    'pos.pm.projects' => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'pm/project/projects'],
    'pos.pm.show' => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'pm/project/show'],

    'pos.pm.house.basic.lists'        => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'pm/house/basic_lists'],
    'pos.pm.house.cells.lists'        => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'pm/house/cells_lists'],
    'pos.pm.house.basic.tree'        => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'pm/house/basic_tree'],
    'pos.pm.house.property.detail'   => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'pm/house/property_detail'],
    'pos.pm.house.update'             => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'pm/house/update'],

    'pos.device.lists'     => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'pm/device/lists'],

    'pos.pm.house.isUpdated' => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'pm/house/is_updated'],
    'pos.pm.house.setUpdated' => ['module' => 'pos', 'controller' => 'dispatch', 'action' => 'pm/house/set_updated'],


    //项目签章信息
    'admin.signature.add' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/signature/add'],
    'admin.signature.update' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/signature/update'],
    'admin.signature.show' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/signature/show'],

    //欠费列表
    'admin.pm.house.arrears.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/arrears/lists'],
    'admin.pm.house.arrears.total' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/arrears/total'],

    'admin.pm.vendor.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/vendor/lists'],
    
    //岗位管理
    'admin.pm.job.add' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/Job/add'],
    'admin.pm.job.update' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/Job/update'],
    'admin.pm.job.show' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/Job/show'],
    'admin.pm.job.lists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/Job/lists'],
    'admin.pm.job.treeLists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/Job/treeLists'],
    
    //新版架构接口
    'admin.pm.frameV2.show' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/FrameV2/show'],
    'admin.pm.frameV2.add' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/FrameV2/add'],
    'admin.pm.frameV2.update' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/FrameV2/update'],
    'admin.pm.frameV2.treeLists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/FrameV2/treeLists'],

    //停车场配置
    'admin.pm.station.show' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/Stationcfg/show'],
    'admin.pm.station.refresh' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/Stationcfg/refresh'],

    // 导入
    'admin.import.msg' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/Import/msg'],
    'admin.import.status' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/Import/status'],
    'admin.import.history' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/Import/history'],

    // 导出
    'admin.export.msg' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/Export/msg'],
    'admin.export.status' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'pm/Export/status'],
];