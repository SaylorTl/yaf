<?php
final class Agreement extends Base
{
    public function lists($params = [])
    {
        if (!isTrueKey($params, 'page', 'pagesize')) rsp_die_json(10001, 'page pagesize 参数缺失或错误');

        if (isset($params['company_name']) && trim($params['company_name']) !== '')  {
            $company_ids = $this->company->post('/corporate/lists', ['company_name' => $params['company_name']]);
            $company_ids = ($company_ids['code'] === 0 && $company_ids['content']) ? $company_ids['content'] : [];
            if (!$company_ids) rsp_success_json(['total' => 0, 'lists' => []]);
            $params['company_ids'] = array_unique(array_filter(array_column($company_ids, 'company_id')));
        }
        if (isTrueKey($params, 'space_id')) {
            $space_ids = $this->pm->post('/space/children', ['space_id' => $params['space_id']]);
            $space_ids = ($space_ids['code'] === 0 && $space_ids['content']) ? $space_ids['content'] : [];
            if (!$space_ids) rsp_success_json(['total' => 0, 'lists' => []]);
            unset($params['space_id']);
            $params['space_ids'] = array_unique(array_filter(array_column($space_ids, 'space_id')));
        }

        $lists = $this->agreement->post('/agreement/lists', $params);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        unset($params['page'], $params['pagesize']);
        $total = $this->agreement->post('/agreement/count', $params);
        if ($total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => 1, 'lists' => $lists['content']]);

        // sum
        $sum = $this->agreement->post('/agreement/sum', $params);
        $sum = ($sum['code'] === 0 && $sum['content']) ? $sum['content'] : [];

        // 公司
        $company_ids = $project_ids = [];
        foreach ($lists['content'] ?: [] as $item) {
            $company_ids = array_merge($company_ids, array_column($item['agreement_accounts'], 'company_id'));
            $company_ids = array_merge($company_ids, array_column($item['agreement_companies'], 'company_id'));
            $company_ids = array_merge($company_ids, array_column($item['agreement_liaisons'], 'company_id'));
            $company_ids = array_merge($company_ids, array_column($item['agreement_payments'], 'company_id'));

            $project_ids = array_merge($project_ids, array_column($item['agreement_projects'], 'project_id'));
        }
        $company_ids = array_unique($company_ids);
        $companies = $this->company->post('/corporate/lists', ['company_ids' => $company_ids]);
        $companies = ($companies['code'] === 0 && $companies['content']) ? many_array_column($companies['content'], 'company_id') : [];

        // 项目
        $projects = $this->pm->post('/project/projects', ['project_ids' => $project_ids]);
        $projects = ($projects['code'] === 0 && $projects['content']) ? many_array_column($projects['content'], 'project_id') : [];

        // 员工
        $employee_ids = array_unique(array_merge(array_filter(array_column($lists['content'], 'created_by')), array_filter(array_column($lists['content'], 'updated_by'))));
        $employees = $this->user->post('/employee/lists',['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        // 文件
        $file_ids = [];
        foreach ($lists['content'] as $item) {
            foreach ($item['agreement_files'] ?: [] as $v) $file_ids[] = $v['file_id'];
        }
        $files = $this->fileupload->post('/list',['file_ids' => $file_ids]);
        $files = ($files['code'] === 0 && $files['content']) ? many_array_column($files['content'], 'file_id') : [];
        $files = array_map(function ($m){
            $file_attributes = $m['file_attributes'] ? json_decode($m['file_attributes'], true) : [];
            return $file_attributes['name'] ?? '';
        },$files);

        $result = array_map(function ($m) use ($companies, $projects, $employees, $files) {
            $m['agreement_accounts'] = array_map(function ($v) use ($companies) {
                $v['company_name'] = getArraysOfvalue($companies, $v['company_id'], 'company_name');
                return $v;
            }, $m['agreement_accounts']);

            $m['agreement_companies'] = array_map(function ($v) use ($companies) {
                $v['company_name'] = getArraysOfvalue($companies, $v['company_id'], 'company_name');
                return $v;
            }, $m['agreement_companies']);

            $m['agreement_liaisons'] = array_map(function ($v) use ($companies) {
                $v['company_name'] = getArraysOfvalue($companies, $v['company_id'], 'company_name');
                return $v;
            }, $m['agreement_liaisons']);

            $m['agreement_payments'] = array_map(function ($v) use ($companies) {
                $v['company_name'] = getArraysOfvalue($companies, $v['company_id'], 'company_name');
                return $v;
            }, $m['agreement_payments']);

            $m['agreement_projects'] = array_map(function ($v) use ($projects) {
                $v['project_name'] = getArraysOfvalue($projects, $v['project_id'], 'project_name');
                return $v;
            }, $m['agreement_projects']);

            $m['agreement_files'] = array_map(function ($v) use ($files) {
                return [
                    'fileId' => $v['file_id'],
                    'fileName' => $files[$v['file_id']] ?? '',
                    'remark' => $v['remark'],
                ];
            }, $m['agreement_files']);

            $m['created_by'] = getArraysOfvalue($employees, $m['created_by'], 'full_name');
            $m['updated_by'] = getArraysOfvalue($employees, $m['updated_by'], 'full_name');

            return $m;
        }, $lists['content']);

        rsp_success_json(['total' => (int)$total['content'], 'lists' => $result, 'sum' => $sum]);
    }

    public function agreements($params = [])
    {
        $lists = $this->agreement->post('/agreement/agreements', $params);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        unset($params['page'], $params['pagesize']);
        $total = $this->agreement->post('/agreement/count', $params);
        if ($total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => 0, 'lists' => $lists['content']]);

        rsp_success_json(['total' => (int)$total['content'], 'lists' => $lists['content']]);
    }

    public function show($params = [])
    {
        $show = $this->agreement->post('/agreement/show', $params);
        if ($show['code'] !== 0 || !$show['content']) rsp_success_json([]);
        rsp_success_json($show['content']);
    }

    public function add($params = [])
    {
        $fields = [
            'agreement_code', 'agreement_name',
            'currency_type_tag_id', 'agreement_amount',
            'agreement_type_tag_id', 'agreement_status_tag_id', 'agreement_fund_tag_id',
            'agreement_files',
            'agreement_companies',
        ];
        if ($diff_fields = get_empty_fields($fields, $params)) rsp_die_json(10001, '缺少参数 '.implode(' ', $diff_fields));

        foreach (json_decode($params['agreement_companies']  ?? '', true) ?: [] as $item){
            if (!isTrueKey($item, 'company_type', 'company_id')) rsp_die_json(10001, '合同方信息不完整');
        }

        $params['agreement_projects'] = json_encode(array_filter(array_map(function ($m) {
            return get_empty_fields(['project_id'], $m) === ['project_id'] ? null : $m;
        }, json_decode($params['agreement_projects'] ?? '', true) ?: [])));

        $params['agreement_liaisons'] = json_encode(array_filter(array_map(function ($m) {
            return get_empty_fields(['company_id', 'liaison_name', 'liaison_job', 'liaison_mobile', 'liaison_email'], $m) === ['company_id', 'liaison_name', 'liaison_job', 'liaison_mobile', 'liaison_email'] ? null : $m;
        }, json_decode($params['agreement_liaisons'] ?? '', true) ?: [])));

        $params['agreement_accounts'] = json_encode(array_filter(array_map(function ($m) {
            return get_empty_fields(['company_id', 'account_name', 'account_identifier', 'account_bank', 'account_keeping_bank', 'account_number', 'account_address', 'account_tel'], $m) === ['company_id', 'account_name', 'account_identifier', 'account_bank', 'account_keeping_bank', 'account_number', 'account_address', 'account_tel'] ? null : $m;
        }, json_decode($params['agreement_accounts'] ?? '', true) ?: [])));

        $params['agreement_payments'] = json_encode(array_filter(array_map(function ($m) {
            return get_empty_fields(['company_id', 'receipt_payment_time', 'payment_amount', 'payment_ratio', 'payment_status_tag_id', 'payment_receipt_time'], $m) === ['company_id', 'receipt_payment_time', 'payment_amount', 'payment_status_tag_id', 'payment_receipt_time'] ? null : $m;
        }, json_decode($params['agreement_payments'] ?? '', true) ?: [])));

        $params['agreement_files'] = json_encode(array_map(function ($m) {
            return [
                'file_id' => $m['fileId'],
                'remark' => $m['remark'],
            ];
        }, json_decode($params['agreement_files'] ?? '', true) ?: []));

        $params['agreement_id'] = resource_id_generator(self::RESOURCE_TYPES['agreement']);
        if (!$params['agreement_id']) rsp_die_json(10001, '生成资源ID失败');

        // todo 添加到用户微服务


        $params['created_at'] =  $params['updated_at'] = time();
        $params['created_by'] =  $params['updated_by'] = $this->employee_id;

        $result = $this->agreement->post('/agreement/add', $params);
        if ($result['code'] !== 0) rsp_die_json(10001, $result['message']);
        rsp_success_json($result['content']);
    }

    public function update($params = [])
    {
        $fields = [
            'agreement_id', 'agreement_code', 'agreement_name',
            'currency_type_tag_id', 'agreement_amount',
            'agreement_type_tag_id', 'agreement_status_tag_id', 'agreement_fund_tag_id',
            'agreement_files',
            'agreement_companies',
        ];
        if ($diff_fields = get_empty_fields($fields, $params)) rsp_die_json(10001, '缺少参数 '.implode(' ', $diff_fields));

        foreach (json_decode($params['agreement_companies']  ?? '', true) ?: [] as $item){
            if (!isTrueKey($item, 'company_type', 'company_id')) rsp_die_json(10001, '合同方信息不完整');
        }

        $params['agreement_projects'] = json_encode(array_filter(array_map(function ($m) {
            return get_empty_fields(['project_id'], $m) === ['project_id'] ? null : $m;
        }, json_decode($params['agreement_projects'] ?? '', true) ?: [])));

        $params['agreement_liaisons'] = json_encode(array_filter(array_map(function ($m) {
            return get_empty_fields(['company_id', 'liaison_name', 'liaison_job', 'liaison_mobile', 'liaison_email'], $m) === ['company_id', 'liaison_name', 'liaison_job', 'liaison_mobile', 'liaison_email'] ? null : $m;
        }, json_decode($params['agreement_liaisons'] ?? '', true) ?: [])));

        $params['agreement_accounts'] = json_encode(array_filter(array_map(function ($m) {
            return get_empty_fields(['company_id', 'account_name', 'account_identifier', 'account_bank', 'account_keeping_bank', 'account_number', 'account_address', 'account_tel'], $m) === ['company_id', 'account_name', 'account_identifier', 'account_bank', 'account_keeping_bank', 'account_number', 'account_address', 'account_tel'] ? null : $m;
        }, json_decode($params['agreement_accounts'] ?? '', true) ?: [])));

        $params['agreement_payments'] = json_encode(array_filter(array_map(function ($m) {
            return get_empty_fields(['company_id', 'receipt_payment_time', 'payment_amount', 'payment_ratio', 'payment_status_tag_id', 'payment_receipt_time'], $m) === ['company_id', 'receipt_payment_time', 'payment_amount', 'payment_status_tag_id', 'payment_receipt_time'] ? null : $m;
        }, json_decode($params['agreement_payments'] ?? '', true) ?: [])));

        $params['agreement_files'] = json_encode(array_map(function ($m) {
            return [
                'file_id' => $m['fileId'],
                'remark' => $m['remark'],
            ];
        }, json_decode($params['agreement_files'] ?? '', true) ?: []));

        $params['updated_at'] = time();
        $params['updated_by'] = $this->employee_id;

        $result = $this->agreement->post('/agreement/update', $params);
        if ($result['code'] !== 0) rsp_die_json(10001, $result['message']);
        rsp_success_json($result['content']);
    }
}