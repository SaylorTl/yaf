<?php

return [
    'admin.file.cos.token'   =>  ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'files/Qcloud/token'],
    'admin.file.info'        =>  ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'files/Files/info'],
    'admin.file.bind'        =>  ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'files/Files/bind'],

    'app.file.cos.token'   =>  ['module' => 'app', 'controller' => 'dispatch', 'action' => 'files/Qcloud/token'],
    'app.file.info'        =>  ['module' => 'app', 'controller' => 'dispatch', 'action' => 'files/Files/info'],
    'app.file.bind'        =>  ['module' => 'app', 'controller' => 'dispatch', 'action' => 'files/Files/bind'],
];