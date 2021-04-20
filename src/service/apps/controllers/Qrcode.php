<?php


class QrcodeController extends Yaf_Controller_Abstract
{
    protected $device;

    protected $pm;

    protected $resource;

    protected $resource_id;

    public function init()
    {
        Yaf_Dispatcher::getInstance()->disableView();
        $this->device = new Comm_Curl([ 'service'=>'device','format'=>'json']);
        $this->pm = new Comm_Curl([ 'service'=>'pm','format'=>'json']);
        $this->resource = new Comm_Curl([ 'service'=>'resource','format'=>'json']);

        $resource_lite = array_values(array_filter(array_reverse(explode('/', $_SERVER['REQUEST_URI']))))[0];
        if (!$resource_lite) rsp_die_json(10001, '缺少参数');
        $this->resource_id = $this->resource->post('/resource/id/lite', ['resource_lite' => $resource_lite]);
        $this->resource_id = $this->resource_id['content'] ?? '';
        if (!$this->resource_id) rsp_die_json(10001, '资源ID查询失败');
    }

    public function deviceAction()
    {
        $device_info = $this->device->post('/device/lists', ['device_id' => $this->resource_id]);
        $device_info = $device_info['content'][0] ?? [];
        if (!$device_info) rsp_die_json(10001, '设备查询失败');
        header("Location: https://w.aparcar.cn/p/qr/{$device_info['device_extcode']}");
        exit();
    }

    public function projectAction()
    {
        $project_info = $this->pm->post('/project/lists', ['project_id' => $this->resource_id]);
        $project_info = $project_info['content'][0] ?? [];
        if (!$project_info) rsp_die_json(10001, '项目查询失败');
        header("Location: https://w.aparcar.cn/p/q/{$project_info['vendor_code']}");
        exit();
    }
}