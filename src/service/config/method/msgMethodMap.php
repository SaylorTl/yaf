<?php

return [
//    'admin.msg.sending'            => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'msg/sending/lists'],
//    'admin.msg.redis.handle'              => ['module' => 'manage', 'controller' => 'dispatch', 'action' => 'msg/redis/handle'],

    'app.msg.sending'            => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'msg/sending/sendmsg'],
    'app.msg.redis.handle'              => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'msg/msgredis/handle'],
];
