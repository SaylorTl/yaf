<?php

namespace Wechat;

class ConstantModel
{
    const REDIS_KEY = [
        'qrcode_temp' => 'wechat:qrtemp_ticket_',
        'qrcode_forever' => 'wechat:qrforever_ticket',
        'wx_msg_to_user' => 'MIDDLEWARE:WX_PASSIVELY_REPLY_TO_USER_MESSAGES'
    ];
}