<?php

class Base
{
    const RESOURCE_TYPES = [
        'project' => 10001,
        'space' => 10006,
        'park_place' => 10007,
        'house' => 10010,
        'frame' => 10002,
        'device' => 10009,
        'cells' => 10015,
        'repair' => 10019,
        'readmeter' => 10020,
        'mediation' => 10024,
        'facility' => 10021,
        'plants' => 10022,
        'yardrent' => 10023,
        'mch' => 10028,
        'job' => 10039,
    ];
    
    protected $pm;
    
    protected $tag;
    
    protected $device;
    
    protected $company;
    
    protected $user;
    
    protected $agreement;
    
    protected $fileupload;
    
    protected $resource;

    protected $lumenscript;

    protected $employee_id;
    
    protected $project_id;
    
    public function __construct()
    {
        $this->device = new Comm_Curl(['service' => 'device', 'format' => 'json']);
        $this->pm = new Comm_Curl(['service' => 'pm', 'format' => 'json']);
        $this->tag = new Comm_Curl(['service' => 'tag', 'format' => 'json']);
        $this->company = new Comm_Curl(['service' => 'company', 'format' => 'json']);
        $this->user = new Comm_Curl(['service' => 'user', 'format' => 'json']);
        $this->agreement = new Comm_Curl(['service' => 'agreement', 'format' => 'json']);
        $this->fileupload = new Comm_Curl(['service' => 'fileupload', 'format' => 'json']);
        $this->resource = new Comm_Curl(['service' => 'resource', 'format' => 'json']);
        $this->lumenscript = new Comm_Curl(['service' => 'lumenscript', 'format' => 'json']);
        $this->employee_id = $_SESSION['employee_id'] ?? '';
        $this->project_id = $_SESSION['member_project_id'] ?? '';
    }
    
    /**
     * 校验标签
     * @param  array  $tag_ids
     * @return bool|null
     */
    protected function checkTag($tag_ids)
    {
        if (!is_array($tag_ids) || empty($tag_ids)) {
            return false;
        }
        $tag_ids = array_filter(array_unique($tag_ids));
        if (empty($tag_ids)) {
            return null;
        }
        $tag_info = $this->tag->post('/tag/lists', ['tag_ids' => $tag_ids, 'nolevel' => 'Y']);
        if ($tag_info['code'] != 0) {
            rsp_die_json(10002, '标签校验查询信息失败');
        } elseif (empty($tag_info['content'])) {
            rsp_die_json(10001, '非法的标签¹ID：'.implode('、', $tag_ids));
        }
        $exists_tag_ids = array_column($tag_info['content'], 'tag_id');
        $faker_tag_ids = array_diff($tag_ids, $exists_tag_ids);
        if ($faker_tag_ids) {
            rsp_die_json(10001, '非法的标签²ID：'.implode('、', $faker_tag_ids));
        }
        return true;
    }
}

