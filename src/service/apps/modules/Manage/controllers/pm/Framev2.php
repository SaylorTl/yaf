<?php

class Framev2 extends Base
{
    /**
     * 架构信息查询
     * @param  array  $params
     */
    public function show($params = [])
    {
        log_message('---frame/'.__FUNCTION__.'---'.json_encode($params));
        if (!isTrueKey($params, 'frame_id')) {
            rsp_die_json(10001, 'frame_id 参数缺失');
        }
        $res = $this->pm->post('/frame/show', ['frame_id' => $params['frame_id']]);
        if ($res['code'] != 0) {
            rsp_die_json(10002, '架构信息查询失败，'.$res['message']);
        } elseif (empty($res['content'])) {
            rsp_success_json();
        }
        $frame_info = $res['content'];
        //查询父级架构信息
        $frame_info['parent_frame_name'] = '';
        if ($frame_info['frame_parent']) {
            $res = $this->pm->post('/frame/show', ['frame_id' => $frame_info['frame_parent']]);
            $frame_info['parent_frame_name'] = $res['content']['frame_name'] ?? '';
        }
        //查询标签信息
        $tag_ids = array_filter(array_unique([
            $frame_info['status_tag_id'],
            $frame_info['type_tag_id'],
        ]));
        $res = $this->tag->post('/tag/lists', ['tag_ids' => $tag_ids, 'nolevel' => 'Y']);
        $tag_info = $res['code'] == 0 ? many_array_column($res['content'], 'tag_id') : [];
        $frame_info['type_name'] = isset($tag_info[$frame_info['type_tag_id']])
            ? $tag_info[$frame_info['type_tag_id']]['tag_name']
            : '';
        $frame_info['status_name'] = isset($tag_info[$frame_info['status_tag_id']])
            ? $tag_info[$frame_info['status_tag_id']]['tag_name']
            : '';
        rsp_success_json($frame_info);
    }
    
    /**
     * 架构岗位树
     * @param  array  $params
     */
    public function treeLists($params = [])
    {
        //架构
        $frame_info = $this->getFrameInfo($params);
        if ($frame_info === false) {
            rsp_die_json(10002, '架构信息查询失败');
        } elseif (empty($frame_info)) {
            rsp_success_json();
        }
        $frame_ids = array_column($frame_info, 'frame_ids');
        $job_info = [];
        if (!isset($params['no_job']) || $params['no_job'] === false) {//岗位
            $job_info = $this->getJobInfo($params + ['frame_ids' => $frame_ids]);
            if ($frame_info === false) {
                rsp_die_json(10002, '岗位信息查询失败');
            }
            $job_info = $this->recursion($job_info);
        }
        $frame_info = $this->recursion($frame_info, 0, $job_info);
        rsp_success_json($frame_info);
    }
    
    /**
     * 获取架构信息
     * @param $params
     * @return array|bool
     */
    private function getFrameInfo($params)
    {
        if (isTrueKey($params, 'deleted')) {
            $params['is_delete'] = $params['deleted'];
        } else {
            $params['is_delete'] = 'N';
        }
        $frame_info = $this->pm->post('/frameV2/simpleLists', $params + ['page' => 0, 'pagesize' => 0]);
        if ($frame_info['code'] != 0) {
            log_message('---Framev2/'.__FUNCTION__.'---'.json_encode(['error' => $frame_info]));
            return false;
        }
        return array_map(function ($m) {
            $res['id'] = $m['frame_id'];
            $res['pid'] = $m['frame_parent'];
            $res['type'] = 'frame';
            $res['name'] = $m['frame_name'];
            return $res;
        }, $frame_info['content']);
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
        $tag_info = $this->tag->post('/tag/lists', ['tag_ids' => $tag_ids, 'nolevel' => 'Y']);
        $tag_info = $tag_info['code'] == 0 ? many_array_column($tag_info['content'], 'tag_id') : [];
        
        return array_map(function ($m) use ($tag_info) {
            $res['id'] = $m['job_id'];
            $res['pid'] = $m['job_parent_id'];
            $res['frame_id'] = $m['frame_id'];
            $res['type'] = 'job';
            $res['name'] = getArraysOfvalue($tag_info, $m['job_name_tag_id'], 'tag_name');
            return $res;
        }, $job_info['content']);
    }
    
    /**
     * 递归处理
     * @param $data
     * @param  int  $pid
     * @param  array  $job_info
     * @return array
     */
    private function recursion($data, $pid = 0, $job_info = [])
    {
        $arr = [];
        if (empty($data) || !is_array($data)) {
            return $arr;
        }
        foreach ($data as $value) {
            if ($value['pid'] == $pid) {
                if ($value['type'] == 'job' && $value['pid'] != 0) {
                    unset($value['frame_id']);
                }
                $value['children'] = $this->recursion($data, $value['id'], $job_info);
                if ($value['type'] == 'frame' && $job_info) {
                    foreach ($job_info as $item) {
                        if ($item['frame_id'] == $value['id']) {
                            $item['pid'] = $item['frame_id'];
                            unset($item['frame_id']);
                            $value['children'][] = $item;
                        }
                    }
                }
                $arr[] = $value;
            }
        }
        return $arr;
    }
    
    /**
     * 添加
     * @param  array  $params
     */
    public function add($params = [])
    {
        log_message(__METHOD__.'-----'.json_encode($params));
        if (!isTrueKey($params, 'type_tag_id')) {
            rsp_die_json(10001, '缺少架构类型');
        }
        if (!isset($params['frame_name']) || !is_string($params['frame_name'])) {
            rsp_die_json(10001, '缺少架构名称或数据类型错误');
        }
        $params['frame_name'] = trim($params['frame_name']);
        if (mb_strlen($params['frame_name']) < 1) {
            rsp_die_json(10001, '架构名称不能为空或全空格符');
        } elseif (mb_strlen($params['frame_name']) > 25) {
            rsp_die_json(10001, '架构名称长度不能超过25个字符');
        }
        if (isTrueKey($params, 'remark') && mb_strlen($params['remark']) > 300) {
            rsp_die_json(10001, '备注信息不能超过300个字符');
        }
        $frame_id = resource_id_generator(self::RESOURCE_TYPES['frame']);
        if (!$frame_id) {
            rsp_die_json(10005, '架构ID生成失败');
        }
        $add_params = [
            'frame_id' => $frame_id,
            'type_tag_id' => $params['type_tag_id'],
            'frame_name' => trim($params['frame_name']),
            'frame_parent' => $params['frame_parent'] ?? '',
            'status_tag_id' => $params['status_tag_id'] ?? 1298,
            'remark' => $params['remark'] ?? '',
            'is_delete' => $params['is_delete'] ?? 'N',
        ];
        $result = $this->pm->post('/frameV2/add', $add_params);
        if ($result['code'] != 0) {
            rsp_die_json(10005, '添加失败，'.$result['message']);
        }
        rsp_success_json(['frame_id' => $frame_id]);
    }
    
    /**
     * 更新
     * @param  array  $params
     */
    public function update($params = [])
    {
        log_message(__METHOD__.'-----'.json_encode($params));
        if (!isTrueKey($params, 'frame_id')) {
            rsp_die_json(10001, '架构信息参数缺失');
        }
        if (isset($params['frame_name'])) {
            $params['frame_name'] = trim($params['frame_name']);
            if (!is_string($params['frame_name'])) {
                rsp_die_json(10001, '架构名称数据类型错误');
            } elseif (mb_strlen($params['frame_name']) < 1) {
                rsp_die_json(10001, '架构名称不能为空或全空格符');
            } elseif (mb_strlen($params['frame_name']) > 25) {
                rsp_die_json(10001, '架构名称长度不能超过25个字符');
            }
        }
        if (isset($params['remark'])) {
            if (!is_string($params['remark'])) {
                rsp_die_json(10001, '备注信息数据类型错误');
            } elseif (mb_strlen($params['remark']) > 300) {
                rsp_die_json(10001, '备注信息长度不能超过300个字符');
            }
        }
        $result = $this->pm->post('/frameV2/update', $params);
        if ($result['code'] != 0) {
            rsp_die_json(10006, '更新失败，'.$result['message']);
        }
        rsp_success_json('');
    }
}