<?php

/*
 * 员工排班model
 * @author:zmj
 * */

namespace User;
class EmployeeScheduleModel
{
    //班次字段和字段名称映射
    public static $schedule_type_filed_map = [
        'project_id' => '项目id',
        'type_name' => '班次名称',
        'begin_time' => '班次开始时间',
        'end_time' => '班次结束时间',
        'status' => '班次状态'
    ];
}