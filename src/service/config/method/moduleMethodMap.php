<?php

return [

    'admin.module.setMember' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/member/member/setMember'],
    'admin.module.memberList' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/member/member/memberList'],
    'admin.module.getAccessList' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/member/member/getAccessList'],
    'admin.module.setAccess' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/member/member/setAccess'],
    'admin.module.editMember' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/member/member/editMember'],
    'admin.module.delMember' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/member/member/delMember'],

    'admin.module.addPrivlegesModule' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/module/modulepage/addPrivlegesModule'],
    'admin.module.getModule' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/module/modulepage/getModule'],


    'admin.module.moduleTree' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/module/Module/moduleTree'],

    'admin.module.checkPrivleges' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/role/role/checkPrivleges'],
    'admin.module.addRole' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/role/role/addRole'],
    'admin.module.editRole' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/role/role/editRole'],
    'admin.module.roleList' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/role/role/roleList'],

    'admin.module.addRolePrivleges' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/role/Roleprivleges/addRolePrivleges'],
    'admin.module.delRolePrivleges' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/role/Roleprivleges/delRolePrivleges'],
    'admin.module.getRolePrivleges' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/role/Roleprivleges/getRolePrivleges'],

    'admin.module.addRoute' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/route/Route/addRoute'],
    'admin.module.batchAddRoute' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/route/Route/batchAddRoute'],
    'admin.module.routeList' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/route/Route/routeList'],

    'admin.module.addPrivlegesPath' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/system/Privleges/addPrivlegesPath'],
    'admin.module.getPrivlegesTree' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/system/Privleges/getPrivlegesTree'],
    'admin.module.getPrivlegesLists' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/system/Privleges/getPrivlegesLists'],
    'admin.module.updatePrivlegesPath' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/system/Privleges/updatePrivlegesPath'],
    'admin.module.delPrivlegesPath' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/system/Privleges/delPrivlegesPath'],
    'admin.resource.getPrivlegesPath' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/system/Privleges/getPrivlegesPath'],

    'admin.module.getSourceList' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/system/Source/getSourceList'],
    'admin.module.addSource' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/system/Source/addSource'],
    'admin.module.delSource' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/system/Source/delSource'],
    'admin.module.editSource' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/system/Source/editSource'],

    'admin.module.getUserPermission' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/system/Useraccess/getUserPermission'],
    'admin.module.editUserPermission' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/system/Useraccess/editUserPermission'],
    'admin.module.setMemberPrivleges' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/system/Useraccess/setMemberPrivleges'],

    'admin.module.userAdd' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/user/User/userAdd'],
    'admin.module.userList' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/user/User/userList'],
    'admin.module.userEdit' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/user/User/userEdit'],
    'admin.module.userDel' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/user/User/userDel'],

    'admin.module.getUserTree' => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'access/user/User/getUserTree'],

  ];


