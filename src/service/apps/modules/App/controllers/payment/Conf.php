<?php

final class Conf extends Base
{
    public function lists($params = [])
    {
        if (!isTrueKey($params,...['project_id'])) rsp_error_tips(10001, 'project_id');

        // project_mch
        $mch = $this->pm->post('/project/mch/show', ['project_id' => $params['project_id']]);
        $mch = ($mch['code'] === 0 && $mch['content']) ? $mch['content'] : [];
        if (!$mch) rsp_error_tips(10002, '商户');

        $res = Comm_Pay::gateway('app.merchant.conf.lists', [
            'yhy_mch_id' => $mch['yhy_mch_id'],
            'status_tag_id' => self::ENABLED['启用'],
        ]);
        $res = ($res['code'] === 0 && $res['content']) ? $res['content'] : [];
        if (!$res) rsp_success_json(['total'=>0,'lists'=>[]]);
        rsp_success_json($res);
    }
}