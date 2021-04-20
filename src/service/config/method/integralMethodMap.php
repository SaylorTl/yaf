<?php

return [
	//账号查询
    'app.integral.account.show' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'integral/account/openAndShow'],

    //交易记录列表
    'app.integral.transaction.lists' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'integral/transaction/lists'],

    //交易
    'app.integral.transaction.fasttransaction' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'integral/transaction/fasttransaction'],

    //详情
    'app.integral.transaction.detail' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'integral/transaction/detail'],

    //短信验证码
    'app.integral.transaction.smsCaptcha' => ['module' => 'app', 'controller' => 'dispatch', 'action' => 'integral/transaction/smsCaptcha'],
];