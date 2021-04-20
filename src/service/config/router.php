<?php

return array(
    'gateway' => ['type'=>'regex', 'match'=>"#^/gateway#", 'route'=>['module'=>'index','controller'=>'gateway','action'=>'index','method'=>'POST']],
    'jaegertest/pm' => ['type'=>'rewrite', 'match'=>"/jaegertest/pm", 'route'=>['module'=>'index','controller'=>'jaegertest','action'=>'pm','method'=>'get']],
    'jaegertest/user' => ['type'=>'rewrite', 'match'=>"/jaegertest/user", 'route'=>['module'=>'index','controller'=>'jaegertest','action'=>'user','method'=>'get']],
    'jaegertest/access' => ['type'=>'rewrite', 'match'=>"/jaegertest/access", 'route'=>['module'=>'index','controller'=>'jaegertest','action'=>'access','method'=>'get']],
    'jaegertest/wxtoken' => ['type'=>'rewrite', 'match'=>"/jaegertest/wxtoken", 'route'=>['module'=>'index','controller'=>'jaegertest','action'=>'wxtoken','method'=>'get']],
    'test_index' => ['type'=>'regex', 'match'=>'#^/test/([A-Za-z0-9\_]+)#', 'route'=>['controller'=>'Index','action'=>'test','method'=>'GET'],'map'=>[1=>'path']],

    'payCallback' => ['type'=>'regex', 'match'=>'#^/payCallback$#', 'route'=>['module'=>'app','controller'=>'Notify','action'=>'payCallback','method'=>'post']],

    'wechat_post' => ['type'=>'regex', 'match'=>"#^/wechat/msg#", 'route'=>['module'=>'index','controller'=>'Wechatmsg','action'=>'index','method'=>'POST'] ],
    'wechat_get' => ['type'=>'regex', 'match'=>"#^/wechat/msg#", 'route'=>['module'=>'index','controller'=>'Wechatmsg','action'=>'index','method'=>'GET'] ],
    'wechat_qrcode_temp' => ['type'=>'regex', 'match'=>"#^/wechat/qrcode/temp#", 'route'=>['module'=>'index','controller'=>'Wechat','action'=>'createTempQrcode','method'=>'GET'] ],
    'wechat_menu_set' => ['type'=>'regex', 'match'=>"#^/wechat/menu/set#", 'route'=>['module'=>'index','controller'=>'Wechat','action'=>'menuSet','method'=>'POST'] ],

    'device_msg' => ['type'=>'regex', 'match'=>'#^/device/msg#', 'route'=>['module'=>'device','controller'=>'Device','action'=>'msg','method'=>'post']],

    'appid' => ['type'=>'regex', 'match'=>'#^/appid#', 'route'=>['controller'=>'Index','action'=>'appid','method'=>'GET']],

    'wechat_post_common' => ['type'=>'regex', 'match'=>"#^/([A-Za-z0-9]+)/wechat/msg#", 'route'=>['module'=>'index','controller'=>'Wechatmsg','action'=>'index','method'=>'POST'] ],
    'wechat_get_common' => ['type'=>'regex', 'match'=>"#^/([A-Za-z0-9]+)/wechat/msg#", 'route'=>['module'=>'index','controller'=>'Wechatmsg','action'=>'index','method'=>'GET'] ],
    'wechat_qrcode_temp_common' => ['type'=>'regex', 'match'=>"#^/([A-Za-z0-9]+)/wechat/qrcode/temp#", 'route'=>['module'=>'index','controller'=>'Wechat','action'=>'createTempQrcode','method'=>'GET'] ],

    'device_qrcode' => ['type'=>'regex', 'match'=>'#^/d/([A-Za-z0-9]{5})#', 'route'=>['module'=>'index','controller'=>'Qrcode','action'=>'device','method'=>'get']],
    'project_qrcode' => ['type'=>'regex', 'match'=>'#^/p/([A-Za-z0-9]{5})#', 'route'=>['module'=>'index','controller'=>'Qrcode','action'=>'project','method'=>'get']],

    'client_projects' => ['type'=>'regex', 'match'=>'#^/client/projects#', 'route'=>['module'=>'index','controller'=>'Client','action'=>'projects','method'=>'post']],
    'client_resource_lite' => ['type'=>'regex', 'match'=>'#^/client/resource/lite#', 'route'=>['module'=>'index','controller'=>'Client','action'=>'resourceLite','method'=>'post']],

    'auth/client' => ['type'=>'rewrite', 'match'=>"/auth/client", 'route'=>['module'=>'index','controller'=>'Auth','action'=>'bindingClient','method'=>'get']],

    'client_employees' => ['type'=>'regex', 'match'=>'#^/client/employees#', 'route'=>['module'=>'index','controller'=>'Client','action'=>'employees','method'=>'post']],
);
