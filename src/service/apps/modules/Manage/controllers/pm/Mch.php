<?php

class Mch extends Base {

    public function add($params = [])
    {
        if (!isTrueKey($params, ...['project_id', 'status_tag_id'])) rsp_error_tips(10001);
        if (!isTrueKey($params, 'yhy_mch_pub_key') || !isTrueKey($params, 'yhy_mch_pri_key')) {
            $config = getConfig('pay.ini');
            $params['yhy_mch_pub_key'] = base64_encode(file_get_contents($config->pay->yhy_mch_pub_key));
            $params['yhy_mch_pri_key'] = base64_encode(file_get_contents($config->pay->yhy_mch_pri_key));
        }
        $project = $this->pm->post('/project/show', ['project_id' => $params['project_id']]);
        $project = ($project['code'] === 0 && $project['content']) ? $project['content'] : [];
        if (!$project) rsp_error_tips(10002, '项目');

        $yhy_mch_pub_key = $params['yhy_mch_pub_key'];
        $yhy_mch_pri_key = $params['yhy_mch_pri_key'];

        $company_id = isTrueKey($params, 'company_id') ? $params['company_id'] : $project['developer_company_id'];
        $companies = $this->company->post('/corporate/lists', ['company_ids' => [$company_id], 'page' => 1, 'pagesize' => 1]);
        $companies = ($companies['code'] === 0 && $companies['content']) ? $companies['content'] : [];
        $yhy_mch_id = Comm_Pay::gateway('admin.merchant.add', [
            'yhy_mch_pub_key' => $yhy_mch_pub_key,
            'project_id' => $project['project_id'],
            'yhy_mch_name' => $project['project_name'],
            'company_id' => $company_id,
            'yhy_company_name' => $companies[0]['company_name'] ?? '',
            'status_tag_id' => $params['status_tag_id'] ?? 0,
            'created_by' => $this->employee_id,
            'updated_by' => $this->employee_id,
            'business_type_tag_ids' => $params['business_type_tag_ids'] ?? [],
            'remark' => $params['remark'] ?? '',
        ]);
        if ($yhy_mch_id['code'] !== 0) rsp_error_tips($yhy_mch_id['code'], $yhy_mch_id['message']);
        $this->pm->post('/project/mch/add', [
            'project_id' => $project['project_id'],
            'yhy_mch_id' => $yhy_mch_id['content'],
            'yhy_mch_pub_key' => $yhy_mch_pub_key,
            'yhy_mch_pri_key' => $yhy_mch_pri_key,
        ]);
        rsp_success_json($yhy_mch_id['content']);
    }
}