<?php

class Decision extends Base
{
    public function mockList($params = [])
    {
        $keyword = '';
        if (isset($params['keyword']) && is_string($params['keyword'])) {
            $keyword = $params['keyword'];
        }
        $data = [];
        for ($i = 0; $i < 10; $i++) {
            $t = $this->getData();
            $t['_id'] .= $i;
            $t['sid'] .= ($i.'10028');
            $t['name'] .= ('-'.$i);
            $t['description'] .= ('-'.$i);
            $data [] = $t;
        }
        if (mb_strlen($keyword) > 0) {
            $data = array_filter($data, function ($m) use ($keyword) {
                return strpos($m['name'], $keyword) !== false;
            });
        }
        rsp_success_json($data);
    }
    
    private function getData()
    {
        return array(
            'enabled' => true,
            '_id' => '5fa106f50f29ed39700e89f',
            'sid' => '1323527822196625400',
            'name' => '个人所得税税率表',
            'description' => '综合所得适用',
            'facts' =>
                array(
                    0 =>
                        array(
                            'fact' => 'salary',
                            'type' => 'Number',
                        ),
                ),
            'rules' =>
                array(
                    0 =>
                        array(
                            'enabled' => true,
                            'priority' => 1,
                            '_id' => '5fa106f50f29ed39700e89f8',
                            'name' => '第1档',
                            'description' => '不超过36000元的',
                            'if' =>
                                array(
                                    'all' =>
                                        array(
                                            0 =>
                                                array(
                                                    'op' => 'lessThanInclusive',
                                                    'left' =>
                                                        array(
                                                            'var-category' => '员工',
                                                            'var' => 'salary',
                                                            'var-label' => '工资',
                                                            'datatype' => 'Number',
                                                        ),
                                                    'value' =>
                                                        array(
                                                            'content' => '36000',
                                                        ),
                                                ),
                                        ),
                                ),
                            'then' =>
                                array(
                                    'var-assign' =>
                                        array(
                                            0 =>
                                                array(
                                                    'var-category' => '薪资',
                                                    'var' => 'ratio',
                                                    'var-label' => '税率',
                                                    'datatype' => 'Number',
                                                    'value' =>
                                                        array(
                                                            'content' => '0.03',
                                                        ),
                                                ),
                                            1 =>
                                                array(
                                                    'var-category' => '薪资',
                                                    'var' => 'deduction',
                                                    'var-label' => '速算扣除数',
                                                    'datatype' => 'Number',
                                                    'value' =>
                                                        array(
                                                            'content' => '0',
                                                        ),
                                                ),
                                        ),
                                ),
                            'else' =>
                                array(),
                        ),
                    1 =>
                        array(
                            'enabled' => true,
                            'priority' => 1,
                            '_id' => '5fa106f50f29ed39700e89f9',
                            'name' => '第2档',
                            'description' => '超过36000元至144000元的部分',
                            'if' =>
                                array(
                                    'all' =>
                                        array(
                                            0 =>
                                                array(
                                                    'op' => 'greaterThan',
                                                    'left' =>
                                                        array(
                                                            'var-category' => '员工',
                                                            'var' => 'salary',
                                                            'var-label' => '工资',
                                                            'datatype' => 'Number',
                                                        ),
                                                    'value' =>
                                                        array(
                                                            'content' => '36000',
                                                        ),
                                                ),
                                            1 =>
                                                array(
                                                    'op' => 'lessThan',
                                                    'left' =>
                                                        array(
                                                            'var-category' => '员工',
                                                            'var' => 'salary',
                                                            'var-label' => '工资',
                                                            'datatype' => 'Number',
                                                        ),
                                                    'value' =>
                                                        array(
                                                            'content' => '144000',
                                                        ),
                                                ),
                                        ),
                                ),
                            'then' =>
                                array(
                                    'var-assign' =>
                                        array(
                                            0 =>
                                                array(
                                                    'var-category' => '薪资',
                                                    'var' => 'ratio',
                                                    'var-label' => '税率',
                                                    'datatype' => 'Number',
                                                    'value' =>
                                                        array(
                                                            'content' => '0.1',
                                                        ),
                                                ),
                                            1 =>
                                                array(
                                                    'var-category' => '薪资',
                                                    'var' => 'deduction',
                                                    'var-label' => '速算扣除数',
                                                    'datatype' => 'Number',
                                                    'value' =>
                                                        array(
                                                            'content' => '2520',
                                                        ),
                                                ),
                                        ),
                                ),
                            'else' =>
                                array(),
                        ),
                    2 =>
                        array(
                            'enabled' => true,
                            'priority' => 1,
                            '_id' => '5fa106f50f29ed39700e89fa',
                            'name' => '第3档',
                            'description' => '超过144000元至300000元的部分',
                            'if' =>
                                array(
                                    'all' =>
                                        array(
                                            0 =>
                                                array(
                                                    'op' => 'greaterThan',
                                                    'left' =>
                                                        array(
                                                            'var-category' => '员工',
                                                            'var' => 'salary',
                                                            'var-label' => '工资',
                                                            'datatype' => 'Number',
                                                        ),
                                                    'value' =>
                                                        array(
                                                            'content' => '144000',
                                                        ),
                                                ),
                                            1 =>
                                                array(
                                                    'op' => 'lessThan',
                                                    'left' =>
                                                        array(
                                                            'var-category' => '员工',
                                                            'var' => 'salary',
                                                            'var-label' => '工资',
                                                            'datatype' => 'Number',
                                                        ),
                                                    'value' =>
                                                        array(
                                                            'content' => '300000',
                                                        ),
                                                ),
                                        ),
                                ),
                            'then' =>
                                array(
                                    'var-assign' =>
                                        array(
                                            0 =>
                                                array(
                                                    'var-category' => '薪资',
                                                    'var' => 'ratio',
                                                    'var-label' => '税率',
                                                    'datatype' => 'Number',
                                                    'value' =>
                                                        array(
                                                            'content' => '0.2',
                                                        ),
                                                ),
                                            1 =>
                                                array(
                                                    'var-category' => '薪资',
                                                    'var' => 'deduction',
                                                    'var-label' => '速算扣除数',
                                                    'datatype' => 'Number',
                                                    'value' =>
                                                        array(
                                                            'content' => '16920',
                                                        ),
                                                ),
                                        ),
                                ),
                            'else' =>
                                array(),
                        ),
                    3 =>
                        array(
                            'enabled' => true,
                            'priority' => 1,
                            '_id' => '5fa106f50f29ed39700e89fb',
                            'name' => '第4档',
                            'description' => '超过300000元至420000元的部分',
                            'if' =>
                                array(
                                    'all' =>
                                        array(
                                            0 =>
                                                array(
                                                    'op' => 'greaterThan',
                                                    'left' =>
                                                        array(
                                                            'var-category' => '员工',
                                                            'var' => 'salary',
                                                            'var-label' => '工资',
                                                            'datatype' => 'Number',
                                                        ),
                                                    'value' =>
                                                        array(
                                                            'content' => '300000',
                                                        ),
                                                ),
                                            1 =>
                                                array(
                                                    'op' => 'lessThan',
                                                    'left' =>
                                                        array(
                                                            'var-category' => '员工',
                                                            'var' => 'salary',
                                                            'var-label' => '工资',
                                                            'datatype' => 'Number',
                                                        ),
                                                    'value' =>
                                                        array(
                                                            'content' => '420000',
                                                        ),
                                                ),
                                        ),
                                ),
                            'then' =>
                                array(
                                    'var-assign' =>
                                        array(
                                            0 =>
                                                array(
                                                    'var-category' => '薪资',
                                                    'var' => 'ratio',
                                                    'var-label' => '税率',
                                                    'datatype' => 'Number',
                                                    'value' =>
                                                        array(
                                                            'content' => '0.25',
                                                        ),
                                                ),
                                            1 =>
                                                array(
                                                    'var-category' => '薪资',
                                                    'var' => 'deduction',
                                                    'var-label' => '速算扣除数',
                                                    'datatype' => 'Number',
                                                    'value' =>
                                                        array(
                                                            'content' => '31920',
                                                        ),
                                                ),
                                        ),
                                ),
                            'else' =>
                                array(),
                        ),
                    4 =>
                        array(
                            'enabled' => true,
                            'priority' => 1,
                            '_id' => '5fa106f50f29ed39700e89fc',
                            'name' => '第5档',
                            'description' => '超过420000元至660000元的部分',
                            'if' =>
                                array(
                                    'all' =>
                                        array(
                                            0 =>
                                                array(
                                                    'op' => 'greaterThan',
                                                    'left' =>
                                                        array(
                                                            'var-category' => '员工',
                                                            'var' => 'salary',
                                                            'var-label' => '工资',
                                                            'datatype' => 'Number',
                                                        ),
                                                    'value' =>
                                                        array(
                                                            'content' => '420000',
                                                        ),
                                                ),
                                            1 =>
                                                array(
                                                    'op' => 'lessThan',
                                                    'left' =>
                                                        array(
                                                            'var-category' => '员工',
                                                            'var' => 'salary',
                                                            'var-label' => '工资',
                                                            'datatype' => 'Number',
                                                        ),
                                                    'value' =>
                                                        array(
                                                            'content' => '660000',
                                                        ),
                                                ),
                                        ),
                                ),
                            'then' =>
                                array(
                                    'var-assign' =>
                                        array(
                                            0 =>
                                                array(
                                                    'var-category' => '薪资',
                                                    'var' => 'ratio',
                                                    'var-label' => '税率',
                                                    'datatype' => 'Number',
                                                    'value' =>
                                                        array(
                                                            'content' => '0.3',
                                                        ),
                                                ),
                                            1 =>
                                                array(
                                                    'var-category' => '薪资',
                                                    'var' => 'deduction',
                                                    'var-label' => '速算扣除数',
                                                    'datatype' => 'Number',
                                                    'value' =>
                                                        array(
                                                            'content' => '52920',
                                                        ),
                                                ),
                                        ),
                                ),
                            'else' =>
                                array(),
                        ),
                    5 =>
                        array(
                            'enabled' => true,
                            'priority' => 1,
                            '_id' => '5fa106f50f29ed39700e89fd',
                            'name' => '第6档',
                            'description' => '超过660000元至960000元的部分',
                            'if' =>
                                array(
                                    'all' =>
                                        array(
                                            0 =>
                                                array(
                                                    'op' => 'greaterThan',
                                                    'left' =>
                                                        array(
                                                            'var-category' => '员工',
                                                            'var' => 'salary',
                                                            'var-label' => '工资',
                                                            'datatype' => 'Number',
                                                        ),
                                                    'value' =>
                                                        array(
                                                            'content' => '660000',
                                                        ),
                                                ),
                                            1 =>
                                                array(
                                                    'op' => 'lessThan',
                                                    'left' =>
                                                        array(
                                                            'var-category' => '员工',
                                                            'var' => 'salary',
                                                            'var-label' => '工资',
                                                            'datatype' => 'Number',
                                                        ),
                                                    'value' =>
                                                        array(
                                                            'content' => '960000',
                                                        ),
                                                ),
                                        ),
                                ),
                            'then' =>
                                array(
                                    'var-assign' =>
                                        array(
                                            0 =>
                                                array(
                                                    'var-category' => '薪资',
                                                    'var' => 'ratio',
                                                    'var-label' => '税率',
                                                    'datatype' => 'Number',
                                                    'value' =>
                                                        array(
                                                            'content' => '0.35',
                                                        ),
                                                ),
                                            1 =>
                                                array(
                                                    'var-category' => '薪资',
                                                    'var' => 'deduction',
                                                    'var-label' => '速算扣除数',
                                                    'datatype' => 'Number',
                                                    'value' =>
                                                        array(
                                                            'content' => '85920',
                                                        ),
                                                ),
                                        ),
                                ),
                            'else' =>
                                array(),
                        ),
                    6 =>
                        array(
                            'enabled' => true,
                            'priority' => 1,
                            '_id' => '5fa106f50f29ed39700e89fe',
                            'name' => '第7档',
                            'description' => '超过960000元的部分',
                            'if' =>
                                array(
                                    'all' =>
                                        array(
                                            0 =>
                                                array(
                                                    'op' => 'greaterThan',
                                                    'left' =>
                                                        array(
                                                            'var-category' => '员工',
                                                            'var' => 'salary',
                                                            'var-label' => '工资',
                                                            'datatype' => 'Number',
                                                        ),
                                                    'value' =>
                                                        array(
                                                            'content' => '960000',
                                                        ),
                                                ),
                                        ),
                                ),
                            'then' =>
                                array(
                                    'var-assign' =>
                                        array(
                                            0 =>
                                                array(
                                                    'var-category' => '薪资',
                                                    'var' => 'ratio',
                                                    'var-label' => '税率',
                                                    'datatype' => 'Number',
                                                    'value' =>
                                                        array(
                                                            'content' => '0.45',
                                                        ),
                                                ),
                                            1 =>
                                                array(
                                                    'var-category' => '薪资',
                                                    'var' => 'deduction',
                                                    'var-label' => '速算扣除数',
                                                    'datatype' => 'Number',
                                                    'value' =>
                                                        array(
                                                            'content' => '181920',
                                                        ),
                                                ),
                                        ),
                                ),
                            'else' =>
                                array(),
                        ),
                ),
            'createdAt' => '2020-11-03T07:29:57.450Z',
            'updatedAt' => '2020-11-03T07:29:57.450Z',
            '__v' => 0,
        );
    }
}