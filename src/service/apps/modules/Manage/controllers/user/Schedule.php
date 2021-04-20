<?php
use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use \PhpOffice\PhpSpreadsheet\IOFactory;
use \User\EmployeeScheduleModel;

/*
 * 排班控制器
 * author:zmj
 * */

final class Schedule extends Base
{

    //资源id映射
    public $resource_map = [
        'employee' => 10012,
    ];
    /**
     * 班次列表
     * @param array $post
     * return json
     * */
    public function typeList($post = [])
    {
        unsetEmptyParams($post);
        if (!isTrueKey($post, 'page', 'pagesize') && !isTrueKey($post, 'not_paging')) rsp_die_json(10001, 'page pagesize 或not_paging参数缺失或错误');

        if (!isTrueKey($post, 'project_id')) {
            rsp_die_json(10001, '项目id不能为空');
        }
        //获取项目名称
        $project_result = $this->pm->post('/project/projects', ['project_id' => $post['project_id']]);
        if ($project_result['code'] != 0) {
            rsp_die_json(10002, $project_result['message']);
        }
        if (empty($project_result['content'])) {
            rsp_die_json(10002, '项目信息为空');
        }
        $project_lists = many_array_column($project_result['content'], 'project_id');

        $search_where = [
            'project_id' => $post['project_id'],
            'page' => isTrueKey($post, 'page') ? $post['page'] : '',
            'pagesize' => isTrueKey($post, 'pagesize') ? $post['pagesize'] : '',
            'type_name_f' => empty($post['type_name_f']) ? '' : $post['type_name_f'],
            'type_name' => empty($post['type_name']) ? '' : $post['type_name'],
            'status' => isset($post['status']) ? $post['status'] : '',
            'type_ids' => empty($post['type_ids']) ? [] : $post['type_ids'],
            'not_paging' => isset($post['not_paging']) ? (int)$post['not_paging'] : 0,
        ];
        $result = $this->user->post('/employee/schedule_type/lists', $search_where);
        if ($result['code'] != 0 ){
            rsp_die_json(10002,$result['message']);
        }
        if ($result['code']==0 && empty($result['content'])) {
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }

        unset($search_where['page']);
        unset($search_where['pagesize']);
        unset($search_where['not_paging']);
        //获取总记录数
        $count_result = $this->user->post('/employee/schedule_type/count', $search_where);
        if ($count_result['code'] != 0) {
            rsp_die_json(10002,$result['message']);
        }

        //员工
        $employee_ids = array_unique(array_merge(array_filter(array_column($result['content'], 'creator')), array_filter(array_column($result['content'], 'editor'))));
        $employees = $this->user->post('/employee/lists',['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        $data = array_map(function($m)use($employees, $project_lists){
            $m['creator_name'] = getArraysOfvalue($employees, $m['creator'], 'full_name');
            $m['editor_name'] = getArraysOfvalue($employees, $m['editor'], 'full_name');
            $m['project_name'] = getArraysOfvalue($project_lists, $m['project_id'], 'project_name');
            return $m;
        }, $result['content']);

        rsp_success_json(['count' => (int)$count_result['content'], 'lists' => $data]);
    }

    /**
     * 新增班次
     * @param array $post
     * return json
     * */
    public function typeAdd($post = [])
    {
        //检查必填参数
        $must_params = ['project_id','type_name','begin_time','end_time','status'];
        if ($diff_fields = get_empty_fields($must_params, $post)) {
            $schedule_type_field = EmployeeScheduleModel::$schedule_type_filed_map;
            $field_name = [];
            foreach ($diff_fields as $val) {
                $field_name[] = $schedule_type_field[$val];
            }

            rsp_die_json(10001, implode('、', $field_name).'不能为空');
        }

        //班次名称
        $type_name = trim($post['type_name']);
        //判断班次名称是否已存在
        $where = [
            'project_id' => $post['project_id'],
            'type_name' => $type_name,
        ];
        $name_info = $this->user->post('/employee/schedule_type/count', $where);
        if ($name_info['code'] != 0) {
            $message = empty($name_info['message']) ? '班次信息获取失败' : $name_info['message'];
            rsp_die_json(10001, $message);
        }
        if (!empty($name_info['content'])) {
            rsp_die_json(10002, '班次名称已存在');
        }


        //判断开始、结束时间格式
        if (!validate_date($post['begin_time'], 'H:i:s') || !validate_date($post['end_time'], 'H:i:s')) {
            rsp_die_json(10001, '班次开始时间或结束时间格式错误');
        }

        $date = '2021-01-01 ';
        $begin_time = $date.$post['begin_time'];
        $end_time = $date.$post['end_time'];
        if (strtotime($end_time) <= strtotime($begin_time)) {
            rsp_die_json(10001, '班次结束时间需大于开始时间');
        }

        //判断时间段是否重复
        /*$repeat_where = [
            'time_repeat' => 1,
            'begin_time' => $post['begin_time'],
            'end_time' => $post['end_time'],
            //'status' => 1,
        ];
        $repeat_info = $this->user->post('/employee/schedule_type/count',$repeat_where);
        if ($repeat_info['code'] != 0) {
            rsp_die_json(10003, '班次信息查询失败');
        }
        if ((int)$repeat_info['content']) {
            rsp_die_json(10004, '班次时间和现有的有重叠');
        }*/

        //判断登录是否失效
        $employee_id = $_SESSION['employee_id'];
        if (empty($employee_id)) {
            rsp_die_json(10002, '登录已失效');
        }

        $insertData = [
            'project_id' => $post['project_id'],
            'type_name' => $type_name,
            'begin_time' => $post['begin_time'],
            'end_time' => $post['end_time'],
            'status' => $post['status'],
            'creator' => $employee_id
        ];
        $result = $this->user->post('/employee/schedule_type/add', $insertData);
        if ($result['code'] != 0 || ($result['code'] == 0 && empty($result['content']))) {
            rsp_die_json(10001, $result['message']);
        }
        rsp_success_json($result['content'],'添加成功');
    }

    /**
     * 编辑班次
     * @param array $post
     * return json
     * */
    public function typeUpdate($post = [])
    {
        $must_params = ['type_id','type_name','begin_time','end_time','status'];
        if ($diff_fields = get_empty_fields($must_params, $post)) {
            $schedule_type_field = EmployeeScheduleModel::$schedule_type_filed_map;
            $field_name = [];
            foreach ($diff_fields as $val) {
                $field_name[] = $schedule_type_field[$val];
            }

            rsp_die_json(10001, implode('、', $field_name).'不能为空');
        }

        //判断班次是否存在
        $type_info = $this->user->post('/employee/schedule_type/show', ['type_id' => $post['type_id']]);
        if ($type_info['code'] != 0) {
            rsp_die_json(10001, '班次查询失败');
        }
        if (empty($type_info['content'])) {
            rsp_die_json(10002, '班次不存在');
        }

        //班次名称
        $type_name = trim($post['type_name']);
        //判断班次名称是否已存在
        $where = [
            'project_id' => $post['project_id'],
            'type_name' => $type_name,
            'not_type_ids' => [$post['type_id']]
        ];
        $name_info = $this->user->post('/employee/schedule_type/count', $where);
        if ($name_info['code'] != 0) {
            $message = empty($name_info['message']) ? '班次信息获取失败' : $name_info['message'];
            rsp_die_json(10001, $message);
        }
        if (!empty($name_info['content'])) {
            rsp_die_json(10002, '班次名称已存在');
        }

        //判断开始、结束时间格式
        if (!validate_date($post['begin_time'], 'H:i:s') || !validate_date($post['end_time'], 'H:i:s')) {
            rsp_die_json(10001, '班次开始时间或结束时间格式错误');
        }

        $date = '2021-01-01 ';
        $begin_time = $date.$post['begin_time'];
        $end_time = $date.$post['end_time'];
        if (strtotime($end_time) <= strtotime($begin_time)) {
            rsp_die_json(10001, '班次结束时间需大于开始时间');
        }

        //判断时间段是否重复
        /*$repeat_where = [
            'time_repeat' => 1,
            'begin_time' => $post['begin_time'],
            'end_time' => $post['end_time'],
            'not_type_ids' => [$post['type_id']],
            //'status' => 1,
        ];
        $repeat_info = $this->user->post('/employee/schedule_type/count',$repeat_where);
        if ($repeat_info['code'] != 0) {
            rsp_die_json(10003, '班次信息查询失败');
        }
        if ((int)$repeat_info['content']) {
            rsp_die_json(10004, '班次时间和现有的有重叠');
        }*/

        //判断登录是否失效
        $employee_id = $_SESSION['employee_id'];
        if (empty($employee_id)) {
            rsp_die_json(10002, '登录已失效');
        }

        $updateData = [
            'type_id' => $post['type_id'],
            'type_name' => $type_name,
            'begin_time' => $post['begin_time'],
            'end_time' => $post['end_time'],
            'status' => $post['status'],
            'editor' => $employee_id
        ];
        $result = $this->user->post('/employee/schedule_type/update', $updateData);
        if ($result['code'] != 0) {
            rsp_die_json(10001, $result['message']);
        }


        //班次时间段或名称更改
        if ($type_info['content']['type_name'] != $type_name || $post['begin_time'] != $type_info['content']['begin_time'] || $post['end_time'] != $type_info['content']['end_time']) {
            //增加班次，状态为已删除
            $insertData = [
                'pid' => $post['type_id'],
                'project_id' => $type_info['content']['project_id'],
                'type_name' => $type_info['content']['type_name'],
                'begin_time' => $type_info['content']['begin_time'],
                'end_time' => $type_info['content']['end_time'],
                'status' => '-1',
                'creator' => $employee_id
            ];
            $insert_result = $this->user->post('/employee/schedule_type/add', $insertData);
            if ($insert_result['code'] != 0 || ($insert_result['code'] == 0 && empty($insert_result['content']))) {
                rsp_die_json(10001, $result['message']);
            }
            //今天及之前的改为新创建的班次id
            $updateScheduleTypeData = [
                'search_type_id' => $post['type_id'],
                'replace_type_id' => $insert_result['content']
            ];
            $result = $this->user->post('/employee/schedule/updateScheduleType', $updateScheduleTypeData);
            if ($result['code'] != 0) {
                rsp_die_json(10001, $result['message']);
            }
        }

        rsp_success_json($result['content'],'更新成功');
    }

    /**
     * 排班列表
     * @param array $post
     * return json
     * */
    public function lists($post = [])
    {
        unsetEmptyParams($post);
        //if (!isTrueKey($post, 'page', 'pagesize')) rsp_die_json(10001, 'page pagesize 参数缺失或错误');

        //项目id
        if (!isTrueKey($post, 'project_id')) {
            rsp_die_json(10001, '项目id不能为空');
        }

        //时间筛选条件
        if (!isTrueKey($post, 'year') || !isTrueKey($post, 'month')) {
            rsp_die_json(10001, '请选择时间');
        }

        //print_r(validate_date($post['year'], 'Y'));die;
        //判断时间格式
        if (!is_numeric($post['year']) || (int)$post['year'] != $post['year'] || !is_numeric($post['month']) || (int)$post['month'] != $post['month']) {
            rsp_die_json(10001, '时间参数错误');
        }
        $dates = [];
        $dates_empty = [];


        $days = date('t', mktime(0, 0, 0, $post['month'], 1, $post['year']));
        $day_now = date('j');
        $month_now = date('n');
        $year_now = date('Y');
        //全量与当前年月比较结果，1表示小于当前年月，2表示跟当前年月相等，3表示大于当前年月
        $total_disabled = 2;
        if ((int)$post['year'] == $year_now && (int)$post['month'] == $month_now) { //跟当前年月相等
            for ($i=1; $i<=$days; $i++) {
                $day = ($i<10) ? ('0'.$i) : $i;
                $date = $post['year'].'-'.$post['month'].'-'.$day;
                $disabled = ($i > $day_now) ? false : true;

                $dates[$date] = ['date' => $date, 'disabled' => $disabled];
                $dates_empty[] = ['date' => $date, 'disabled' => $disabled];
            }
        } else {
            //默认比当前小
            $disabled = true;
            $total_disabled = 1;
            //比当前大，年份大于当前或年分相等月份大于当前
            if ((int)$post['year'] > $year_now || ((int)$post['year'] == $year_now && (int)$post['month'] > $month_now)) {
                $disabled = false;
                $total_disabled = 3;
            }

            for ($i=1; $i<=$days; $i++) {
                $day = ($i<10) ? ('0'.$i) : $i;
                $date = $post['year'].'-'.$post['month'].'-'.$day;
                $dates[$date] = ['date' => $date, 'disabled' => $disabled];
                $dates_empty[] = ['date' => $date, 'disabled' => $disabled];
            }
        }



        //获取员工信息
        $employee_where = [
            'page' => isset($post['page']) ? $post['page'] : 1,
            'pagesize' => isset($post['pagesize']) ? $post['pagesize'] : 100,
            'project_id' => $post['project_id'],
            'order' => 'employee_id asc',
        ];
        $employee_result = $this->user->post('/employee/userlist', $employee_where);
        if ($employee_result['code'] != 0) {
            rsp_die_json(10002, $employee_result['message']);
        }
        if ($employee_result['code'] == 0 && empty($employee_result['content']['count'])) {
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }


        $employee_list = $employee_result['content']['lists'];


        //获取员工岗位信息
        $employee_ids = array_column($employee_result['content']['lists'], 'employee_id');
        $job_info = $this->getEmployeeJobInfo($employee_ids);
        $employee_list_ids = array_flip($employee_ids);

        //获取员工排班信息
        $schedule_where = [
            'not_paging' => 1,
            'year' => $post['year'],
            'month' => (int)$post['month'],
            //'employee_ids' => $employee_ids,
            'project_id' => $post['project_id'],
        ];

        $schedule_result = $this->user->post('/employee/schedule/lists', $schedule_where);
        if ($schedule_result['code'] != 0) {
            rsp_die_json(10002, $schedule_result['message']);
        }

        $employee_schedule_list = [];
        $schedule_employee_ids = [];
        if ($total_disabled == 2) { //跟当前年月相等
            $date_now = date('Y-m-d');
            foreach ($schedule_result['content'] as $item) {
                //处理班次信息
                $schedule_type_arr = explode(',', trim($item['schedule_type_ids'], ','));
                $schedule_type_ids = [];
                foreach ($schedule_type_arr as $val) {
                    $schedule_type_ids[] = (int)$val;
                }

                $disabled = ($item['schedule_date'] > $date_now) ? false : true;
                //$item['schedule_type_ids'] = $schedule_type_ids;
                $employee_schedule_list[$item['employee_id']][$item['schedule_date']] = [
                    'date' => $item['schedule_date'],
                    'type_ids' => $schedule_type_ids,
                    'disabled' => $disabled
                    //'project_id' => $item['project_id']
                ];

                //判断是否在员工列表
                if (!isset($employee_list_ids[$item['employee_id']])) {
                    $schedule_employee_ids[] = $item['employee_id'];
                }
            }
        } else {
            $disabled = $total_disabled == 1 ? true : false;
            foreach ($schedule_result['content'] as $item) {
                //处理班次信息
                $schedule_type_arr = explode(',', trim($item['schedule_type_ids'], ','));
                $schedule_type_ids = [];
                foreach ($schedule_type_arr as $val) {
                    $schedule_type_ids[] = (int)$val;
                }
                //$item['schedule_type_ids'] = $schedule_type_ids;
                $employee_schedule_list[$item['employee_id']][$item['schedule_date']] = [
                    'date' => $item['schedule_date'],
                    'type_ids' => $schedule_type_ids,
                    'disabled' => $disabled
                    //'project_id' => $item['project_id']
                ];

                //判断是否在员工列表
                if (!isset($employee_list_ids[$item['employee_id']])) {
                    $schedule_employee_ids[] = $item['employee_id'];
                }
            }
        }

        //有不在员工列表的排班员工
        $schedule_employee_list = [];
        if ($schedule_employee_ids) {
            //获取员工数据
            $schedule_employee_result = $this->user->post('/employee/userlist', ['employee_ids' => $schedule_employee_ids]);
            if ($schedule_employee_result['code'] != 0) {
                rsp_die_json(10002, '获取员工列表数据失败');
            }
            //$employee_list = array_merge($employee_list, $schedule_employee_result['content']);
            $schedule_employee_list = $schedule_employee_result['content']['lists'];

            //获取岗位信息
            $schedule_job_info = $this->getEmployeeJobInfo($schedule_employee_ids);
            $job_info = array_merge($job_info, $schedule_job_info);
        }

        foreach ($employee_schedule_list as $key => $value) {
            //$month_schedule = $value + $dates;
            $month_schedule = array_merge($dates, $value);
            $employee_schedule_list[$key] = array_values($month_schedule);
        }
        //print_r($employee_schedule_list);die;
        $data = array_map(function ($m) use ($employee_schedule_list, $dates_empty, $job_info) {
            $res['schedule'] = isset($employee_schedule_list[$m['employee_id']]) ? $employee_schedule_list[$m['employee_id']] : $dates_empty;
            $res['job_name'] = isset($job_info[$m['employee_id']]) ? $job_info[$m['employee_id']]['job_name'] : '';
            $res['full_name'] = $m['full_name'];
            $res['employee_id'] = $m['employee_id'];
            $res['project_id'] = $m['project_id'];
            $res['status'] = '1';

            return $res;
        }, $employee_list);

        $project_id = $post['project_id'];
        $schedule_data = array_map(function ($m) use ($employee_schedule_list, $dates_empty, $job_info, $project_id) {
            $res['schedule'] = isset($employee_schedule_list[$m['employee_id']]) ? $employee_schedule_list[$m['employee_id']] : $dates_empty;
            $res['job_name'] = isset($job_info[$m['employee_id']]) ? $job_info[$m['employee_id']]['job_name'] : '';
            $res['full_name'] = $m['full_name'];
            $res['employee_id'] = $m['employee_id'];
            $res['project_id'] = $project_id;
            $res['status'] = '0';

            return $res;
        }, $schedule_employee_list);
        $data = array_merge($data, $schedule_data);
        $count = (int)$employee_result['content']['count'] + count($schedule_data);

        rsp_success_json(['count' => $count, 'lists' => $data]);
    }

    /**
     * 排班列表
     * @param array $post
     * return json
     * */
    public function v2_lists($post = [])
    {
        unsetEmptyParams($post);
        //if (!isTrueKey($post, 'page', 'pagesize')) rsp_die_json(10001, 'page pagesize 参数缺失或错误');

        //项目id
        if (!isTrueKey($post, 'project_id')) {
            rsp_die_json(10001, '项目id不能为空');
        }

        //时间筛选条件
        if (!isTrueKey($post, 'year') || !isTrueKey($post, 'month')) {
            rsp_die_json(10001, '请选择时间');
        }

        //print_r(validate_date($post['year'], 'Y'));die;
        //判断时间格式
        if (!is_numeric($post['year']) || (int)$post['year'] != $post['year'] || !is_numeric($post['month']) || (int)$post['month'] != $post['month']) {
            rsp_die_json(10001, '时间参数错误');
        }
        $dates = [];
        $dates_empty = [];


        $days = date('t', mktime(0, 0, 0, $post['month'], 1, $post['year']));
        $day_now = date('j');
        $month_now = date('n');
        $year_now = date('Y');
        $date_now = date('Y-m-d');
        //全量与当前年月比较结果，1表示小于当前年月，2表示跟当前年月相等，3表示大于当前年月
        $total_disabled = 2;
        if ((int)$post['year'] == $year_now && (int)$post['month'] == $month_now) { //跟当前年月相等
            for ($i=1; $i<=$days; $i++) {
                $day = ($i<10) ? ('0'.$i) : $i;
                $date = $post['year'].'-'.$post['month'].'-'.$day;
                $disabled = ($i > $day_now) ? false : true;

                $dates[$date] = ['date' => $date, 'disabled' => $disabled];
                $dates_empty[] = ['date' => $date, 'disabled' => $disabled];
            }
        } else {
            //默认比当前小
            $disabled = true;
            $total_disabled = 1;
            //比当前大，年份大于当前或年分相等月份大于当前
            if ((int)$post['year'] > $year_now || ((int)$post['year'] == $year_now && (int)$post['month'] > $month_now)) {
                $disabled = false;
                $total_disabled = 3;
            }

            for ($i=1; $i<=$days; $i++) {
                $day = ($i<10) ? ('0'.$i) : $i;
                $date = $post['year'].'-'.$post['month'].'-'.$day;
                $dates[$date] = ['date' => $date, 'disabled' => $disabled];
                $dates_empty[] = ['date' => $date, 'disabled' => $disabled];
            }
        }

        $page = isset($post['page']) ? $post['page'] : 1;
        $pagesize = isset($post['pagesize']) ? $post['pagesize'] : 20;
        //获取员工信息
        $employee_where = [
            'page' => $page,
            'pagesize' => $pagesize,
            'project_id' => $post['project_id'],
            'order' => 'employee_id asc',
        ];
        $employee_result = $this->user->post('/employee/userlist', $employee_where);
        if ($employee_result['code'] != 0) {
            rsp_die_json(10002, $employee_result['message']);
        }


        //获取员工已更改项目的历史排班记录数
        $changing_where = [
            //'not_paging' => 1,
            'year' => $post['year'],
            'month' => (int)$post['month'],
            'project_id' => $post['project_id'],
            'project_changing' => 'Y',
            'group_by' => 'employee_id',
            'distinct' => true,
        ];
        $changing_project_employee_result = $this->user->post('/employee/schedule/count', $changing_where);
        if ($changing_project_employee_result['code'] != 0) {
            rsp_die_json(10002, '查询更改项目员工数失败');
        }
        //print_r($changing_project_employee_result);die;
        //无数据，返回空数组
        if (empty($employee_result['content']['count']) && empty($changing_project_employee_result['content'])) {
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }

        //排班数据
        $employee_schedule_list = [];

        $changing_project_employee_count = $changing_project_employee_result['content'];
        $changing_project_employee_ids = [];
        if (count($employee_result['content']['lists']) < $pagesize && $changing_project_employee_count) {
            //获取员工已更改项目的历史排班数据
            unset($changing_where['group_by']);
            unset($changing_where['distinct']);
            $changing_where['not_paging'] = 1;

            $changing_project_schedule_result = $this->user->post('/employee/schedule/lists', $changing_where);
            if ($changing_project_schedule_result['code'] != 0) {
                rsp_die_json(10002, '查询更改项目员工排班数据失败');
            }

            if ($total_disabled == 2) { //跟当前年月相等
                foreach ($changing_project_schedule_result['content'] as $item) {
                    //处理班次信息
                    $schedule_type_arr = explode(',', trim($item['schedule_type_ids'], ','));
                    $schedule_type_ids = [];
                    foreach ($schedule_type_arr as $val) {
                        $schedule_type_ids[] = (int)$val;
                    }
                    //$item['schedule_type_ids'] = $schedule_type_ids;

                    $disabled = ($item['schedule_date'] > $date_now) ? false : true;
                    $employee_schedule_list[$item['employee_id']][$item['schedule_date']] = [
                        'date' => $item['schedule_date'],
                        'type_ids' => $schedule_type_ids,
                        'disabled' => $disabled
                        //'project_id' => $item['project_id']
                    ];
                    $changing_project_employee_ids[$item['employee_id']] = $item['employee_id'];
                }
            } else {
                $disabled = ($total_disabled == 1) ? true : false;
                foreach ($changing_project_schedule_result['content'] as $item) {
                    //处理班次信息
                    $schedule_type_arr = explode(',', trim($item['schedule_type_ids'], ','));
                    $schedule_type_ids = [];
                    foreach ($schedule_type_arr as $val) {
                        $schedule_type_ids[] = (int)$val;
                    }
                    //$item['schedule_type_ids'] = $schedule_type_ids;


                    $employee_schedule_list[$item['employee_id']][$item['schedule_date']] = [
                        'date' => $item['schedule_date'],
                        'type_ids' => $schedule_type_ids,
                        'disabled' => $disabled
                        //'project_id' => $item['project_id']
                    ];
                    $changing_project_employee_ids[$item['employee_id']] = $item['employee_id'];
                }
            }
        }


        $employee_list = $employee_result['content']['lists'];

        //岗位信息
        $job_info = [];
        if ($employee_list) {
            //获取员工岗位信息
            $employee_ids = array_column($employee_result['content']['lists'], 'employee_id');
            $job_info = $this->getEmployeeJobInfo($employee_ids);

            //获取员工排班信息
            $schedule_where = [
                'not_paging' => 1,
                'year' => $post['year'],
                'month' => (int)$post['month'],
                'employee_ids' => $employee_ids,
                'project_id' => $post['project_id'],
            ];

            $schedule_result = $this->user->post('/employee/schedule/lists', $schedule_where);
            if ($schedule_result['code'] != 0) {
                rsp_die_json(10002, $schedule_result['message']);
            }

            if ($total_disabled == 2) { //跟当前年月相等
                foreach ($schedule_result['content'] as $item) {
                    //处理班次信息
                    $schedule_type_arr = explode(',', trim($item['schedule_type_ids'], ','));
                    $schedule_type_ids = [];
                    foreach ($schedule_type_arr as $val) {
                        $schedule_type_ids[] = (int)$val;
                    }
                    //$item['schedule_type_ids'] = $schedule_type_ids;
                    $disabled = ($item['schedule_date'] > $date_now) ? false : true;
                    $employee_schedule_list[$item['employee_id']][$item['schedule_date']] = [
                        'date' => $item['schedule_date'],
                        'type_ids' => $schedule_type_ids,
                        'disabled' => $disabled
                        //'project_id' => $item['project_id']
                    ];

                }
            } else {
                $disabled = ($total_disabled == 1) ? true : false;
                foreach ($schedule_result['content'] as $item) {
                    //处理班次信息
                    $schedule_type_arr = explode(',', trim($item['schedule_type_ids'], ','));
                    $schedule_type_ids = [];
                    foreach ($schedule_type_arr as $val) {
                        $schedule_type_ids[] = (int)$val;
                    }
                    //$item['schedule_type_ids'] = $schedule_type_ids;
                    $employee_schedule_list[$item['employee_id']][$item['schedule_date']] = [
                        'date' => $item['schedule_date'],
                        'type_ids' => $schedule_type_ids,
                        'disabled' => $disabled
                        //'project_id' => $item['project_id']
                    ];

                }
            }

        }


        //有修改项目的排班员工
        $schedule_employee_list = [];
        if ($changing_project_employee_ids) {

            $changing_project_employee_ids = array_values($changing_project_employee_ids);
            //获取员工数据
            $schedule_employee_result = $this->user->post('/employee/userlist', ['employee_ids' => $changing_project_employee_ids]);
            if ($schedule_employee_result['code'] != 0) {
                rsp_die_json(10002, '获取员工列表数据失败');
            }
            //$employee_list = array_merge($employee_list, $schedule_employee_result['content']);
            $schedule_employee_list = $schedule_employee_result['content']['lists'];
            $changing_project_employee_count = $schedule_employee_result['content']['count'];

            //获取岗位信息
            $schedule_job_info = $this->getEmployeeJobInfo($changing_project_employee_ids);
            $job_info = array_merge($job_info, $schedule_job_info);
        }

        //组合用户已有的排班数据
        foreach ($employee_schedule_list as $key => $value) {
            //$month_schedule = $value + $dates;
            $month_schedule = array_merge($dates, $value);
            $employee_schedule_list[$key] = array_values($month_schedule);
        }

        $data = [];
        foreach ($employee_list as $m) {
            $res['schedule'] = isset($employee_schedule_list[$m['employee_id']]) ? $employee_schedule_list[$m['employee_id']] : $dates_empty;
            $res['job_name'] = isset($job_info[$m['employee_id']]) ? $job_info[$m['employee_id']]['job_name'] : '';
            $res['full_name'] = $m['full_name'];
            $res['employee_id'] = $m['employee_id'];
            $res['project_id'] = $m['project_id'];
            $res['status'] = '1';
            $data[] = $res;
        }
        foreach ($schedule_employee_list as $m) {
            $res['schedule'] = isset($employee_schedule_list[$m['employee_id']]) ? $employee_schedule_list[$m['employee_id']] : $dates_empty;
            $res['job_name'] = isset($job_info[$m['employee_id']]) ? $job_info[$m['employee_id']]['job_name'] : '';
            $res['full_name'] = $m['full_name'];
            $res['employee_id'] = $m['employee_id'];
            $res['project_id'] = $m['project_id'];
            $res['status'] = '0';
            $data[] = $res;
        }

        $count = (int)$employee_result['content']['count'] + (int)$changing_project_employee_count;
        rsp_success_json(['count' => $count, 'lists' => $data]);
    }

    /**
     * 获取员工岗位信息
     * @param $employee_ids
     * return array
     * */
    private function getEmployeeJobInfo($employee_ids)
    {
        $employee_ids = array_filter(array_unique($employee_ids));
        $employee_job_info = $this->user->post('/employeejob/lists', ['employee_ids' => $employee_ids]);
        $employee_job_info = $employee_job_info['code'] == 0 ? $employee_job_info['content'] : [];
        $job_ids = array_filter(array_unique(array_column($employee_job_info, 'job_id')));
        $job_info = [];
        if ($job_ids) {
            $job_info = $this->pm->post('/job/simpleLists', ['job_ids' => $job_ids]);

            $job_info = ($job_info['code'] == 0 && !empty($job_info['content'])) ? $job_info['content'] : [];

            $job_name_tag_ids = array_filter(array_unique(array_column($job_info, 'job_name_tag_id')));
            //$frame_ids = array_filter(array_unique(array_column($job_info, 'frame_id')));
            //$tag_info = $frame_info = [];
            $tag_info = [];
            if ($job_name_tag_ids) {
                $tag_info = $this->tag->post('/tag/lists', ['tag_ids' => $job_name_tag_ids, 'nolevel' => 'Y']);
                $tag_info = $tag_info['code'] == 0 ? array_column($tag_info['content'], null, 'tag_id') : [];
            }
            /*if ($frame_ids) {
                $frame_info = $this->pm->post('/frameV2/lists', ['frame_ids' => $frame_ids]);
                $frame_info = $frame_info['code'] == 0 ? array_column($frame_info['content'], null, 'frame_id') : [];
            }*/
            $job_info = array_map(function ($m) use ($tag_info) {
                return [
                    'job_id' => $m['job_id'],
                    'job_name_tag_id' => $m['job_name_tag_id'],
                    'job_name' => getArraysOfvalue($tag_info, $m['job_name_tag_id'], 'tag_name'),
                ];
            }, $job_info);
            $job_info = array_column($job_info, null, 'job_id');
        }
        $employee_job_info = array_map(function ($m) use ($job_info) {
            return [
                'employee_id' => $m['employee_id'],
                'job_id' => $m['job_id'],
                'job_name_tag_id' => getArraysOfvalue($job_info, $m['job_id'], 'job_name_tag_id'),
                'job_name' => getArraysOfvalue($job_info, $m['job_id'], 'job_name'),
            ];
        }, $employee_job_info);

        $data = many_array_column($employee_job_info, 'employee_id');
//        $data = [];
//        foreach ($employee_job_info as $value) {
//            /*$employee_id = $value['employee_id'];
//            unset($value['employee_id']);
//            if( !isset($data[$employee_id]) ){
//                $data[$employee_id] = [];
//            }
//            $data[$employee_id][] = $value;*/
//
//            //获取最新的岗位信息
//            $data[$value['employee_id']] = $value;
//        }
        return $data;
    }

    /**
     * 保存排班信息
     * @param array $post
     * return json
     * */
    public function save($post = [])
    {
        unsetEmptyParams($post);
        $must_params = ['lists'];
        if ($diff_fields = get_empty_fields($must_params, $post)) {
            rsp_die_json(10001, '缺少参数 ' . implode(' ', $diff_fields));
        }

        //判断登录是否失效
        $employee_id = $_SESSION['employee_id'];
        if (empty($employee_id)) {
            rsp_die_json(10002, '登录已失效');
        }

        $updateData = [
            'lists' => $post['lists'],
            'operator' => $employee_id
        ];
        $result = $this->user->post('/employee/schedule/save', $updateData);
        if ($result['code'] != 0) {
            rsp_die_json(10001, $result['message']);
        }

        //结果数据传到事件触发器
        /*$result['content']['app_id'] = $_SESSION['oauth_app_id'];
        $result['content']['sub_app_id'] = $_SESSION['oauth_subapp_id'] ?? 0;
        $res = Comm_EventTrigger::push('employee_schedule_operation_record', $result['content']);
        if ($res == false) {
            rsp_die_json(10005, '员工排班操作日志推送到事件触发器失败');
        }*/

        rsp_success_json('','保存成功');
    }

    /**
     * 导出排班信息
     * @param array $post
     * filestream
     * */
    public function export($post = [])
    {
        //获取当前条件下的数据
        unsetEmptyParams($post);
        //if (!isTrueKey($post, 'page', 'pagesize')) rsp_die_json(10001, 'page pagesize 参数缺失或错误');

        //项目id
        if (!isTrueKey($post, 'project_id')) {
            rsp_die_json(10001, '项目id不能为空');
        }

        //时间筛选条件
        if (!isTrueKey($post, 'year') || !isTrueKey($post, 'month')) {
            rsp_die_json(10001, '请选择时间');
        }

        //print_r(validate_date($post['year'], 'Y'));die;
        //判断时间格式
        if (!is_numeric($post['year']) || (int)$post['year'] != $post['year'] || !is_numeric($post['month']) || (int)$post['month'] != $post['month']) {
            rsp_die_json(10001, '时间参数错误');
        }

        //获取项目名称
        $project_result = $this->pm->post('/project/projects', ['project_id' => $post['project_id']]);
        if ($project_result['code'] != 0) {
            rsp_die_json(10002, $project_result['message']);
        }
        if (empty($project_result['content'])) {
            rsp_die_json(10002, '项目信息为空');
        }

        $file_tile = $project_result['content'][0]['project_name'].'-'.$post['year'].$post['month'].'排班表';
        $newExcel = new Spreadsheet();  //创建一个新的excel文档
        $objSheet = $newExcel->getActiveSheet();  //获取当前操作sheet的对象
        $objSheet->setTitle($file_tile);  //设置当前sheet的标题

        $dates = [];
        $dates_empty = [];

        $sheet_arr = [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G', 8 => 'H', 9 => 'I', 10 => 'J', 11 => 'K', 12 => 'L', 13 => 'M', 14 => 'N', 15 => 'O', 16 => 'P', 17 => 'Q', 18 => 'R', 19 => 'S', 20 => 'T', 21 => 'U', 22 => 'V', 23 => 'W', 24 => 'X', 25 => 'Y', 26 => 'Z', 27 => 'AA', 28 => 'AB', 29 => 'AC', 30 => 'AD', 31 => 'AE', 32 => 'AF', 33 => 'AG', 34 => 'AH', 35 => 'AI'];
        $week_arr = ["星期日","星期一","星期二","星期三","星期四","星期五","星期六"];
        //设置宽度为true
        $objSheet->getColumnDimension('A')->setAutoSize(true);
        $objSheet->getColumnDimension('B')->setAutoSize(true);
        $objSheet->getColumnDimension('C')->setAutoSize(true);
        //设置标题
        $objSheet->mergeCells('A1:A2');
        $objSheet->mergeCells('B1:B2');
        $objSheet->mergeCells('C1:C2');
        $objSheet->setCellValue('A1', '序号');
        $objSheet->setCellValue('B1', '员工');
        $objSheet->setCellValue('C1', '岗位');

        $days = date('t', mktime(0, 0, 0, $post['month'], 1, $post['year']));
        for($i=1; $i<=$days; $i++) {
            $day = ($i<10) ? ('0'.$i) : $i;
            $date = $post['year'].'-'.$post['month'].'-'.$day;
            $dates[$date] = [];
            $dates_empty[] = [];

            //设置宽度为true
            $newExcel->getActiveSheet()->getColumnDimension($sheet_arr[($i + 3)])->setAutoSize(true);
            //设置单元格标题
            $sheet1 = $sheet_arr[($i + 3)].'1';
            $sheet2 = $sheet_arr[($i + 3)].'2';

            $objSheet->setCellValue($sheet1, ($post['year'].'年'.$post['month'].'月'.$day.'日'));
            $objSheet->setCellValue($sheet2, $week_arr[date('w', mktime(0, 0, 0, $post['month'], $i, $post['year']))]);
        }

        //获取员工信息
        $employee_where = [
            'not_paging' => 1,
            'project_id' => $post['project_id'],
            'order' => 'employee_id asc',
        ];
        $employee_result = $this->user->post('/employee/userlist', $employee_where);
        if ($employee_result['code'] != 0) {
            rsp_die_json(10002, $employee_result['message']);
        }
        /*if ($employee_result['code'] == 0 && empty($employee_result['content']['count'])) {
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }*/
        $employee_list = $employee_result['content']['lists'];
        //获取员工岗位信息
        $employee_ids = array_column($employee_result['content']['lists'], 'employee_id');
        $job_info = $this->getEmployeeJobInfo($employee_ids);
        //$employee_list_ids = many_array_column($employee_list, 'employee_id');
        $employee_list_ids = array_flip($employee_ids);

        //获取员工排班信息
        $schedule_where = [
            'not_paging' => 1,
            'year' => $post['year'],
            'month' => (int)$post['month'],
            'project_id' => $post['project_id']
        ];
        $schedule_result = $this->user->post('/employee/schedule/lists', $schedule_where);
        if ($schedule_result['code'] != 0) {
            rsp_die_json(10002, $schedule_result['message']);
        }

        //获取班次信息
        $schedule_type_result = $this->user->post('/employee/schedule_type/lists', ['project_id' => $post['project_id'], 'not_paging' => 1]);
        if ($schedule_type_result['code'] != 0) {
            rsp_die_json(10002, $schedule_type_result['message']);
        }
        $schedule_type_list = many_array_column($schedule_type_result['content'], 'type_id');

        $employee_schedule_list = [];
        $schedule_employee_ids = [];
        foreach ($schedule_result['content'] as $item) {
            //处理班次信息
            $schedule_type_arr = explode(',', trim($item['schedule_type_ids'], ','));
            $schedule_type_name = [];
            foreach ($schedule_type_arr as $val) {
                $schedule_type_name[] = isset($schedule_type_list[$val]) ? ($schedule_type_list[$val]['type_name'].'    '.$schedule_type_list[$val]['begin_time'].'~'.$schedule_type_list[$val]['end_time']) : '';
            }
            //$item['schedule_type_ids'] = $schedule_type_ids;
            $employee_schedule_list[$item['employee_id']][$item['schedule_date']] = [
                'schedule_type_name' => implode(PHP_EOL, $schedule_type_name),
            ];

            //判断是否在员工列表
            if (!isset($employee_list_ids[$item['employee_id']])) {
                $schedule_employee_ids[] = $item['employee_id'];
            }
        }

        if ($schedule_employee_ids) {
            //获取员工数据
            $schedule_employee_result = $this->user->post('/employee/lists', ['employee_ids' => $schedule_employee_ids]);
            if ($schedule_employee_result['code'] != 0) {
                rsp_die_json(10002, '获取员工列表数据失败');
            }
            $employee_list = array_merge($employee_list, $schedule_employee_result['content']);

            //获取岗位信息
            $schedule_job_info = $this->getEmployeeJobInfo($schedule_employee_ids);
            $job_info = array_merge($job_info, $schedule_job_info);
        }


        foreach ($employee_schedule_list as $key => $value) {
            $month_schedule = array_merge($dates, $value);
            /*$month_schedule = array_filter(array_values($month_schedule));
            if (empty($month_schedule)) {
                $num++;
                continue;
            }
            foreach ($month_schedule as $ke => $val) {
                $sheet = $sheet_arr[($ke+1)].$num;
                $objSheet->setCellValue($sheet, $val['schedule_type_name']);
                $objSheet->getStyle($sheet)->getAlignment()->setWrapText(true);
            }*/
            $employee_schedule_list[$key] = array_values($month_schedule);
        }

        //数据列起始值
        $num = 2;
        foreach ($employee_list as $key => $value) {
            $num++;

            $job_name = isset($job_info[$value['employee_id']]) ? $job_info[$value['employee_id']]['job_name'] : '';
            $objSheet->setCellValue('A'.$num, ($key +1));
            $objSheet->setCellValue('B'.$num, $value['full_name']);
            $objSheet->setCellValue('C'.$num, $job_name);

            if (!isset($employee_schedule_list[$value['employee_id']])) {
                continue;
            }
            $month_schedule = array_filter($employee_schedule_list[$value['employee_id']]);
            foreach ($month_schedule as $ke => $val) {
                $sheet = $sheet_arr[($ke+4)].$num;
                $objSheet->setCellValue($sheet, $val['schedule_type_name']);
                $objSheet->getStyle($sheet)->getAlignment()->setWrapText(true);
            }
        }

        $dir = DATA_PATH.'/schedule_attachment';
        $filepath = $this->downloadExcel($newExcel, $file_tile, 'Xlsx', $dir);

        //调用文件微服务
        $resource_id = resource_id_generator($this->resource_map['employee']);
        if (!$resource_id) {
            rsp_die_json(10002, '资源id生成失败');
        }

        if (empty($_SESSION['oauth_app_id'])) {
            rsp_die_json(10002, '登录失效');
        }

        $config = getConfig('ms.ini');
        $url = $config->get('fileupload.url');
        $url .= '/upload';

        $header = ['Oauth-App-Id:'.$_SESSION['oauth_app_id']];
        $file_data = [
            'resource_type' => 'employee',
            'resource_id' => $resource_id
        ];

        $res = $this->curl_upload($url, $header, $filepath, $file_data);
        if ($res == false) {
            rsp_die_json(10002, '上传文件失败');
        }
        if ($res['code'] != 0) {
            rsp_die_json(10003, $res['message']);
        }

        $file_info = pathinfo($filepath);
        //删除文件
        unlink($filepath);
        rsp_success_json(['id' => $resource_id, 'name' => $file_info['basename']],'导出成功');
    }

    /**
     * 保存或输出文件
     * */
    public function downloadExcel($newExcel, $filename, $format, $dir = '')
    {
        $filename .=  '.' . strtolower($format);

        $objWriter = IOFactory::createWriter($newExcel, $format);
        if ($dir) {
            $path = $dir.'/'.$filename;
            //通过php保存在本地的时候需要用到
            $objWriter->save($path);
            return $path;
        } else {
            // $format只能为 Xlsx 或 Xls
            if ($format == 'Xlsx') {
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            } elseif ($format == 'Xls') {
                header('Content-Type: application/vnd.ms-excel');
            }


            header("Content-Disposition: attachment;filename=". $filename);
            header('Cache-Control: max-age=0');

            $objWriter->save('php://output');

            //以下为需要用到IE时候设置

            // If you're serving to IE 9, then the following may be needed

            //header('Cache-Control: max-age=1');

            // If you're serving to IE over SSL, then the following may be needed

            //header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past

            //header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified

            //header('Cache-Control: cache, must-revalidate'); // HTTP/1.1

            //header('Pragma: public'); // HTTP/1.0

            exit;
        }

    }

    /**
     * 发送文件上传请求
     * */
    public function curl_upload($url, $header, $filepath, $params = [])
    {
        //$filepath = DATA_PATH.'/attachment/20200410092053.jpg';
        $file_data = [
            'file' => new \CURLFile(realpath($filepath))
        ];
        $post_data = array_merge($file_data, $params);
        //log_message('post data:'.json_encode($post_data));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_HEADER, true);
        $temp = curl_exec($ch);
        $err_no = curl_error($ch);
        if ($err_no) {
            log_message('文件上传异常---msg:'.json_encode($temp));
            return false;
        }


        $errno = curl_errno($ch);
        if ($errno != 0) {
            log_message('文件上传失败，错误码：'.$errno);
            return false;
        }
        curl_close($ch);
        //print_r($errno);die;
        return json_decode($temp, true);
    }

}



