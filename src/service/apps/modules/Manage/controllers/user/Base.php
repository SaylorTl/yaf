<?php
include_once APP_PATH . "/BaseController.php";

use User\ConstantModel as Constant;

class Base
{
    public $user;
    public $tag;
    public $pm;
    public $access;
    public $auth2;
    public $face;
    public $file;
    public $device;
    public $msg;
    public $station_adapter;

    public function __construct()
    {
        $this->user = new Comm_Curl(['service' => 'user', 'format' => 'json']);
        $this->tag = new Comm_Curl(['service' => 'tag', 'format' => 'json']);
        $this->pm = new Comm_Curl(['service' => 'pm', 'format' => 'json']);
        $this->resource = new Comm_Curl(['service' => 'resource', 'format' => 'json']);
        $this->access = new Comm_Curl(['service' => 'access', 'format' => 'json']);
        $this->route = new Comm_Curl(['service' => 'route', 'format' => 'json']);
        $this->auth2 = new Comm_Curl(['service' => 'auth2', 'format' => 'json']);
        $this->face = new Comm_Curl(['service' => 'face', 'format' => 'json']);
        $this->file = new Comm_Curl(['service' => 'fileupload', 'format' => 'json']);
        $this->device = new Comm_Curl(['service' => 'device', 'format' => 'json']);
        $this->msg = new Comm_Curl(['service' => 'msg', 'format' => 'json']);
        $this->station_adapter = new Comm_Curl(['service' => 'station_adapter', 'format' => 'json']);
    }

    /**
     * @param $car_lists
     * @param $params
     * @return array
     * 停车场适配器月卡查询
     */
    public function station_adapter_contract($car_lists, $params)
    {
        $params['project_id'] = $params['project_id'] ?? $_SESSION['member_project_id'];
        if (!isTrueKey($params, 'mobile', 'project_id')) {
            return $car_lists;
        }
        $show = $this->pm->post('/project/show', ['project_id' => $params['project_id']]);
        if ($show['code'] != 0 || empty($show['content'])) {
            return $car_lists;
        }
        $project = $show['content'];
        if ($project['linkage_payment'] != 'Y' || $project['ownership_company_tag_id'] != 1307) {
            return $car_lists;
        }

        $contracts = $this->station_adapter->post('/ep/contract/lists', $params);
        if ($contracts['code'] != 0 || empty($contracts['content']['lists'])) {
            return $car_lists;
        }
        $mergeCarInfo = [];
        $checkContracts = twoArraySetColumns($contracts['content']['lists'], Constant::CONTRACT_COLUMNS);
        foreach ($checkContracts as $k => $v) {
            if (empty($car_lists)) {
                continue;
            }
            foreach ($car_lists as $c => $car) {
                if ($v['plate'] == $car['plate']) {
                    $car['rule'] = $v['rule'];
                } else {
                    $v['car_type_tag_id'] = 349; // 月卡默认为机动车
                    $v['car_type_tag_name'] = '机动车'; // 月卡默认为机动车
                    $mergeCarInfo[] = $v;
                }
            }
        }

        $car_lists = $mergeCarInfo ? array_merge($car_lists, $mergeCarInfo) : $checkContracts;
        return $car_lists;
    }
}