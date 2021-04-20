<?php

namespace Face;

class FaceModel {

    protected $user;

    protected $face;

    protected $file;

    public function  __construct()
    {
        $this->user = new \Comm_Curl([ 'service'=> 'user','format'=>'json']);
        $this->file = new \Comm_Curl(['service' => 'fileupload', 'format' => 'json']);
        $this->face = new \Comm_Curl(['service' => 'face', 'format' => 'json']);
    }

    public function refreshFace(Array $params = [])
    {
        if (!isTrueKey($params, ...['project_id', 'face_resource_id'])) {
            info(__METHOD__, $params);
            rsp_die_json(10001, '人脸相关参数缺失');
        }
        if (!isTrueKey($params, 'tenement_id') && !isTrueKey($params, 'user_id')) {
            info(__METHOD__, $params);
            rsp_die_json(10001, '人脸相关参数缺失');
        }
        $user_id = $params['user_id'] ?? '';
        if (isTrueKey($params, 'tenement_id')) {
            $tenements = $this->user->post('/tenement/userlist', ['page' => 1, 'pagesize' => 1, 'tenement_ids' => [$params['tenement_id']]]);
            $tenements = ($tenements['code'] === 0 && $tenements['content']) ? $tenements['content']['lists'] : [];
            $user_id = $tenements[0]['user_id'] ?? '';
        }
        if (!$user_id) {
            rsp_die_json(10001, '查询用户id失败');
        }

        $face_file = $this->file->post('/info', ['file_id' => $params['face_resource_id']]);
        $face_file = ($face_file['code'] === 0 && $face_file['content']) ? $face_file['content'] : [];
        $face_res = $this->face->post('/face/refresh', [
            'user_id' => $user_id,
            'project_id' => $params['project_id'],
            'file_id' => $params['face_resource_id'],
            'url' => $face_file['url'] ?? '',
        ]);
        if ($face_res['code'] !== 0) rsp_die_json(10001, $face_res['message'] ?? '人脸检测失败');
        return true;
    }

    public function personExists(Array $params = [])
    {
        if (!isTrueKey($params, ...['project_id', 'face_resource_id'])) {
            info(__METHOD__, $params);
            rsp_die_json(10001, '人脸相关参数缺失');
        }

        $face_file = $this->file->post('/info', ['file_id' => $params['face_resource_id']]);
        $face_file = ($face_file['code'] === 0 && $face_file['content']) ? $face_file['content'] : [];

        $persons = $this->face->post('/person/search', [
            'url' => $face_file['url'] ?? '',
        ]);
        $persons = ($persons['code'] === 0 && $persons['content']) ? $persons['content'] : [];
        if (!$persons) return false;

        $exist = false;
        foreach ($persons as $item) {
            if ($item['group_id'] === $params['project_id']) {
                $exist = true;
                break;
            }
        }
        return $exist;
    }
}