<?php

class Job extends Base
{
    /**
     * 添加岗位
     * @param  array  $params
     */
    public function add($params = [])
    {
        log_message('---Job/'.__FUNCTION__.'---'.json_encode($params));
        if (!isTrueKey($params, 'job_name_tag_id')) {
            rsp_error_tips(10001, '岗位名称标签信息缺失');
        }
        if (!isTrueKey($params, 'frame_id') && !isTrueKey($params, 'job_parent_id')) {
            rsp_die_json(10001, 'frame_id 和 job_parent_id 必须存在其中一个参数');
        }
        if (isTrueKey($params, 'remark') && is_string($params['remark']) && mb_strlen($params['remark']) > 300) {
            rsp_die_json(10001, '备注信息字符数不能超过300，当前：'.mb_strlen($params['remark']));
        }
        //标签校验
        $this->checkTag([
            $params['job_name_tag_id'] ?? '',
            $params['status_tag_id'] ?? '',
            $params['work_shift_tag_id'] ?? ''
        ]);
        if (isTrueKey($params, 'space_ids')) {
            $this->checkSpace($params['space_ids']);
        }
        $params['job_id'] = resource_id_generator(self::RESOURCE_TYPES['job']);
        if (!$params['job_id']) {
            rsp_die_json(10005, '岗位ID创建失败');
        }
        if (!isTrueKey($params, 'status_tag_id')) {
            $params['status_tag_id'] = 1298;
        }
        $params['created_by'] = $this->employee_id;
        $add_res = $this->pm->post('/job/add', $params);
        if ($add_res['code'] != 0) {
            rsp_die_json(10005, '添加失败，'.$add_res['message']);
        }
        if (isTrueKey($params, 'space_ids') && is_array($params['space_ids'])) {
            $bind_res = $this->pm->post('/job/bindSpace', [
                'job_id' => $add_res['content']['job_id'],
                'space_ids' => $params['space_ids']
            ]);
            if ($bind_res['code'] != 0) {
                rsp_die_json(10005, '岗位添加成功，但管理空间添加失败，'.$bind_res['message']);
            }
        }
        rsp_success_json($add_res['content']);
    }
    
    /**
     * 更新岗位
     * @param  array  $params
     */
    public function update($params = [])
    {
        log_message('---Job/'.__FUNCTION__.'---'.json_encode($params));
        if (!isTrueKey($params, 'job_id')) {
            rsp_die_json(10001, 'job_id 参数缺失');
        }
        if (isTrueKey($params, 'remark') && is_string($params['remark']) && mb_strlen($params['remark']) > 300) {
            rsp_die_json(10001, '备注信息字符数不能超过300，当前：'.mb_strlen($params['remark']));
        }
        //标签校验
        $this->checkTag([
            $params['job_name_tag_id'] ?? '',
            $params['status_tag_id'] ?? '',
            $params['work_shift_tag_id'] ?? ''
        ]);
        if (isTrueKey($params, 'space_ids')) {
            $this->checkSpace($params['space_ids']);
        }
        $params['updated_by'] = $this->employee_id;
        $add_res = $this->pm->post('/job/update', $params);
        if ($add_res['code'] != 0) {
            rsp_die_json(10005, '更新失败，'.$add_res['message']);
        }
        if (isset($params['space_ids']) && is_array($params['space_ids'])) {
            $params['space_ids'][] = "0"; //防止空数组传递不过去
            $bind_res = $this->pm->post('/job/bindSpace', $params);
            if ($bind_res['code'] != 0) {
                rsp_die_json(10005, '管理空间更新失败，'.$add_res['message']);
            }
        }
        rsp_success_json([]);
    }
    
    /**
     * 查询岗位详情
     * @param  array  $params
     */
    public function show($params = [])
    {
        log_message('---Job/'.__FUNCTION__.'---'.json_encode($params));
        if (!isTrueKey($params, 'job_id')) {
            rsp_die_json(10001, 'job_id 参数缺失');
        }
        $res = $this->pm->post('/job/lists', ['job_id' => $params['job_id'], 'page' => 1, 'pagesize' => 1]);
        if ($res['code'] != 0) {
            rsp_die_json(10002, '岗位信息查询失败，'.$res['message']);
        } elseif (empty($res['content'])) {
            rsp_success_json();
        }
        $job_info = array_pop($res['content']);
        //查询架构信息
        $frame_info = $this->pm->post('/frame/show', ['frame_id' => $job_info['frame_id']]);
        $job_info['frame_name'] = $frame_info['content']['frame_name'] ?? '';
        //查询标签信息
        $tag_ids = array_filter(array_unique([
            $job_info['work_shift_tag_id'],
            $job_info['status_tag_id'],
            $job_info['job_name_tag_id'],
        ]));
        //父级岗位信息
        $job_info['parent_job_name_tag_id'] = 0;
        if ($job_info['job_parent_id']) {
            $res = $this->pm->post('/job/simpleLists', [
                'job_id' => $job_info['job_parent_id'],
                'page' => 1,
                'pagesize' => 1
            ]);
            $job_info['parent_job_name_tag_id'] = $tag_ids[] = ($res['code'] == 0 && $res['content'])
                ? $res['content'][0]['job_name_tag_id'] : 0;
        }
        $res = $this->tag->post('/tag/lists', ['tag_ids' => $tag_ids, 'nolevel' => 'Y']);
        $tag_info = $res['code'] == 0 ? many_array_column($res['content'], 'tag_id') : [];
        $job_info['work_shift_name'] = isset($tag_info[$job_info['work_shift_tag_id']])
            ? $tag_info[$job_info['work_shift_tag_id']]['tag_name']
            : '';
        $job_info['status_name'] = isset($tag_info[$job_info['status_tag_id']])
            ? $tag_info[$job_info['status_tag_id']]['tag_name']
            : '';
        $job_info['job_name'] = isset($tag_info[$job_info['job_name_tag_id']])
            ? $tag_info[$job_info['job_name_tag_id']]['tag_name']
            : '';
        $job_info['parent_job_name'] = isset($tag_info[$job_info['parent_job_name_tag_id']])
            ? $tag_info[$job_info['parent_job_name_tag_id']]['tag_name']
            : '';
        //空间信息
        $job_info['space_info'] = [];
        if ($job_info['space_ids']) {
            $res = $this->pm->post('/space/lists', ['space_ids' => $job_info['space_ids']]);
            $space_info = ($res['code'] == 0 && $res['content']) ? many_array_column($res['content'], 'space_id') : [];
            foreach ($job_info['space_ids'] as $value) {
                $job_info['space_info'][] = [
                    'space_id' => $value,
                    'space_name' => isset($space_info[$value]) ? $space_info[$value]['space_name'] : '',
                ];
            }
        }
        $job_info['created_at'] = $job_info['created_at'] ? date('Y-m-d H:i:s', $job_info['created_at']) : '';
        $job_info['updated_at'] = $job_info['updated_at'] ? date('Y-m-d H:i:s', $job_info['updated_at']) : '';
        $job_info['work_shift_tag_id'] = $job_info['work_shift_tag_id'] ?: null; //兼容前端回填显示
        rsp_success_json($job_info);
    }
    
    /**
     * 岗位树
     * @param  array  $params
     */
    public function treeLists($params = [])
    {
        if (!isTrueKey($params, 'frame_id')) {
            rsp_die_json(10001, '架构ID缺失');
        }
        $job_info = $this->getJobInfo($params);
        if ($job_info === false) {
            rsp_die_json(10002, '查询失败');
        }
        $job_info = $this->recursion($job_info);
        rsp_success_json($job_info);
    }
    
    /**
     * 获取岗位信息
     * @param $params
     * @return array|bool
     */
    private function getJobInfo($params)
    {
        if (!isTrueKey($params, 'deleted')) {
            $params['deleted'] = 'N';
        }
        $job_info = $this->pm->post('/job/simpleLists', $params + ['page' => 0, 'pagesize' => 0]);
        if ($job_info['code'] != 0) {
            log_message('---Framev2/'.__FUNCTION__.'---'.json_encode(['error' => $job_info]));
            return false;
        } elseif (empty($job_info['content'])) {
            return [];
        }
        //标签信息
        $tag_ids = array_unique(array_column($job_info['content'], 'job_name_tag_id'));
        $tag_ids = array_merge($tag_ids, array_unique(array_column($job_info['content'], 'status_tag_id')));
        $tag_info = $this->tag->post('/tag/lists', ['tag_ids' => $tag_ids, 'nolevel' => 'Y']);
        $tag_info = $tag_info['code'] == 0 ? many_array_column($tag_info['content'], 'tag_id') : [];
        //架构信息
        $frame_ids = array_unique(array_column($job_info['content'], 'frame_id'));
        $frame_info = $this->pm->post('/frameV2/simpleLists',
            ['frame_ids' => $frame_ids, 'page' => 0, 'pagesize' => 0]);
        $frame_info = $frame_info['code'] == 0 ? many_array_column($frame_info['content'], 'frame_id') : [];
        return array_map(function ($m) use ($tag_info, $frame_info) {
            $m['job_name'] = getArraysOfvalue($tag_info, $m['job_name_tag_id'], 'tag_name');
            $m['status_name'] = getArraysOfvalue($tag_info, $m['status_tag_id'], 'tag_name');
            $m['frame_name'] = getArraysOfvalue($frame_info, $m['frame_id'], 'frame_name');
            return $m;
        }, $job_info['content']);
    }
    
    /**
     * 递归处理岗位信息
     * @param $data
     * @param  int  $job_parent_id
     * @return array
     */
    private function recursion($data, $job_parent_id = 0)
    {
        $arr = [];
        if (empty($data) || !is_array($data)) {
            return $arr;
        }
        foreach ($data as $value) {
            if ($value['job_parent_id'] == $job_parent_id) {
                $value['children'] = $this->recursion($data, $value['job_id']);
                $arr[] = $value;
            }
        }
        return $arr;
    }
    
    /**
     * 检查空间ID
     * @param $space_ids
     * @return bool|null
     */
    private function checkSpace($space_ids)
    {
        if (!is_array($space_ids) || empty($space_ids)) {
            return false;
        }
        $space_ids = array_filter(array_unique($space_ids));
        if (empty($space_ids)) {
            return null;
        }
        $space_info = $this->pm->post('/space/lists', [
            'space_ids' => $space_ids,
            'project_id' => $this->project_id,
            'page' => 1,
            'pagesize' => count($space_ids)
        ]);
        if ($space_info['code'] != 0) {
            rsp_die_json(10002, '空间校验查询信息失败');
        } elseif (empty($space_info['content'])) {
            rsp_die_json(10001, '非法的空间¹ID：'.implode('、', $space_ids));
        }
        $exists_space_ids = array_column($space_info['content'], 'space_id');
        $faker_space_ids = array_diff($space_ids, $exists_space_ids);
        if ($faker_space_ids) {
            rsp_die_json(10001, '非法的空间²ID：'.implode('、', $faker_space_ids));
        }
        return true;
    }
}
