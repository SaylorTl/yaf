<?php
/**
 * Created by PhpStorm.
 * User: 18716
 * Date: 2020/2/24
 * Time: 16:07
 */

namespace Project;

class ConstantModel
{
    /**
     * 项目文件参数
     */
    const FILES = [
        'fileId',
        'file_type_tag_id',
        'file_description'
    ];

    /**
     * 周边关系参数
     */
    const HOAS = [
        'person_name',
        'person_job',
        'person_mobile',
        'person_email',
        'person_address',
        'person_term_begin',
        'person_term_end',
        'person_type_tag_id',
        'person_tenement_type_tag_id',
        'tenement_id',
        'person_remark'
    ];

}