<?php

final class tag extends Base
{
    public function lists($params = [])
    {
        if (!isTrueKey($params, 'page', 'pagesize')) rsp_error_tips(10001, "page、pagesize");
        $where = ['page' => $params['page'], 'pagesize' => $params['pagesize'], 'tag_status' => 'Y'];
        if (isTrueKey($params, 'type_id')) $where['type_id'] = $params['type_id'];
        if (isset($params['tag_name'])) $where['tag_name'] = $params['tag_name'];
        if (isTrueKey($params, 'tag_status')) $where['tag_status'] = $params['tag_status'];
        if (isTrueKey($params, 'type_ids')) $where['type_ids'] = $params['type_ids'];
        if (isTrueKey($params, 'app_id')) $where['app_id'] = $params['app_id'];
        if (isTrueKey($params, 'module_id')) {
            $tag_type = $this->tag->post('/tag/type/lists', ['module_id' => $params['module_id']]);
            if ($tag_type['code'] == 0 && !empty($tag_type['content'])) {
                $type_ids = array_filter(array_unique(array_column($tag_type['content'], 'type_id')));
                $where['type_ids'] = implode(',', $type_ids);
            }
        }

        if (isTrueKey($params, 'module_ids')) {
            if (strpos($params['module_ids'], ',') == false) rsp_die_json(10002, 'module_ids参数错误');
            $tag_type = $this->tag->post('/tag/type/lists', ['module_ids' => $params['module_ids']]);
            if ($tag_type['code'] == 0 && !empty($tag_type['content'])) {
                $type_ids = array_filter(array_unique(array_column($tag_type['content'], 'type_id')));
                $where['type_ids'] = implode(',', $type_ids);
            }
        }

        $data = $this->tag->post('/tag/lists', $where);
        if ($data['code'] !== 0) rsp_error_tips(10002, $data['message']);
        if (empty($data['content'])) rsp_success_json([], $data['message']);

        $count = $this->tag->post('/tag/count', $where);
        $num = $count['code'] == 0 ? $count['content'] : 0;

        $tag_type_ids = array_filter(array_unique(array_column($data['content'], 'type_id')));
        $types = $this->tag->post('/tag/type/lists', ['type_ids' => implode(',', $tag_type_ids)]);
        $tag_types = $types ? many_array_column($types['content'], 'type_id') : [];

        $tag_module_ids = array_filter(array_unique(array_column($types['content'], 'module_id')));
        $tmp = $this->tag->post('/tag/module/nolevel/lists', ['module_ids' => $tag_module_ids]);
        $tag_module = $tmp ? many_array_column($tmp['content'], 'module_id') : [];

        $lists = array_map(function ($m) use ($tag_types, $tag_module) {
            $m['type_name'] = getArraysOfvalue($tag_types, $m['type_id'], 'type_name');
            $m['module_id'] = getArraysOfvalue($tag_types, $m['type_id'], 'module_id');
            $m['module_name'] = getArraysOfvalue($tag_module, $m['module_id'], 'module_name');
            unset($m['creationtime'], $m['modifiedtime']);
            return $m;
        }, $data['content']);

        $res = [];
        if (isTrueKey($params, 'module_ids')) {
            foreach ($lists as $k => $v) {
                $res[$v['module_name']][$v['type_name']][] = $v;
            }
        } else {
            foreach ($lists as $k => $v) {
                $res[$v['type_name']][] = $v;
            }
        }

        rsp_success_json(['total' => $num, 'lists' => $res], '查询成功');
    }

    public function add($params = [])
    {
        $info = [];
        if (isTruekey($params, 'type_id')) {
            $info['type_id'] = $params['type_id'];
        } else {
            rsp_error_tips(10001, 'type_id');
        }

        if (isset($params['tag_name'])) {
            $info['tag_name'] = $params['tag_name'];
        } else {
            rsp_error_tips(10001, 'tag_name');
        }

        if (isTruekey($params, 'tag_val')) {
            $info['tag_val'] = $params['tag_val'];
        }

        if (isTruekey($params, 'tag_sort')) {
            $info['tag_sort'] = $params['tag_sort'];
        }

        $show = $this->tag->post('/tag/show', ['type_id'=>$info['type_id'],'tag_name'=>$info['tag_name']]);
        if ($show['code'] == 0 && !empty($show['content'])) rsp_error_tips(10003);

        $result = $this->tag->post('/tag/add', $info);
        if ($result['code'] != 0) rsp_error_tips(10005);

        rsp_success_json(['tag_id' => $result['content']], '添加成功');
    }

    public function delete($params = [])
    {
        if (!isTrueKey($params, 'tag_id')) rsp_error_tips(10001, 'tag_id');
        $result = $this->tag->post('/tag/delete', ['tag_id' => $params['tag_id']]);
        if (!$result) rsp_error_tips(10009);
        rsp_success_json(1);
    }

    public function cache_clean()
    {
        $this->tag->post('/tag/cache/clean', []);
        rsp_success_json(1);
    }


}