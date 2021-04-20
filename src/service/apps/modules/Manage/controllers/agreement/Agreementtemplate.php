<?php
final class Agreementtemplate extends Base
{
    public function lists($params = [])
    {
        if (!isTrueKey($params, 'page', 'pagesize')) rsp_die_json(10001, 'page pagesize 参数缺失或错误');

        $lists = $this->agreement->post('/agreement/template/lists', $params);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);

        unset($params['page'], $params['pagesize']);
        $total = $this->agreement->post('/agreement/template/count', $params);
        if ($total['code'] !== 0 || !$total['content']) rsp_success_json(['total' => 1, 'lists' => $lists['content']]);

        // 员工
        $employee_ids = array_unique(array_merge(array_filter(array_column($lists['content'], 'created_by')), array_filter(array_column($lists['content'], 'updated_by'))));
        $employees = $this->user->post('/employee/lists',['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        rsp_success_json(['total' => (int)$total['content'], 'lists' => array_map(function ($m) use ($employees) {
            $m['created_by'] = getArraysOfvalue($employees, $m['created_by'], 'full_name');
            $m['updated_by'] = getArraysOfvalue($employees, $m['updated_by'], 'full_name');
            return $m;
        }, $lists['content'])]);
    }

    public function show($params = [])
    {
        $show = $this->agreement->post('/agreement/template/show', $params);
        if ($show['code'] !== 0 || !$show['content']) rsp_success_json([]);
        rsp_success_json($show['content']);
    }

    public function add($params = [])
    {
        $fields = [
            'agreement_template_code', 'agreement_template_name', 'agreement_template_file_id', 'agreement_template_status_tag_id',
        ];
        if ($diff_fields = get_empty_fields($fields, $params)) rsp_die_json(10001, '缺少参数 '.implode(' ', $diff_fields));
        $agreement_template_id = resource_id_generator(self::RESOURCE_TYPES['agreement_template']);
        if (!$agreement_template_id) rsp_die_json(10001, '生成资源ID失败');
        $time = time();
        $result = $this->agreement->post('/agreement/template/add', [
            'agreement_template_id' => $agreement_template_id,
            'agreement_template_code' => $params['agreement_template_code'],
            'agreement_template_name' => $params['agreement_template_name'],
            'agreement_template_file_id' => $params['agreement_template_file_id'],
            'agreement_template_remark' => $params['agreement_template_remark'] ?? '',
            'agreement_template_status_tag_id' => $params['agreement_template_status_tag_id'],
            'created_at' => $time,
            'created_by' => $this->employee_id,
            'updated_at' => $time,
            'updated_by' => $this->employee_id,
        ]);
        if ($result['code'] !== 0) rsp_die_json(10001, $result['message']);
        rsp_success_json($agreement_template_id);
    }

    public function update($params = [])
    {
        if (!isTrueKey($params, 'agreement_template_id')) rsp_die_json(10001, '缺少参数 agreement_template_id');
        $result = $this->agreement->post('/agreement/template/update', array_merge($params, ['updated_at' => time(), 'updated_by' => $this->employee_id]));
        if ($result['code'] !== 0) rsp_die_json(10001, $result['message']);
        rsp_success_json(1);
    }
}