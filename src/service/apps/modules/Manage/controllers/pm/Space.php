<?php

final class Space extends Base
{
    public function lists ($params = [])
    {
        if (!isTrueKey($params,'page','pagesize')) rsp_error_tips(10001, "page、pagesize");
        $params['project_id'] = $this->project_id;
        $data = $this->pm->post('/space/lists',$params);
        if ($data['code'] !== 0) rsp_error_tips(10002);
        if (empty($data['content'])) rsp_success_json(['total' => 0,'lists' => []],$data['message']);

        $count = $this->pm->post('/space/count',$params);
        $num = $count['code'] == 0 ? $count['content'] : 0 ;

        $project_ids = array_filter(array_unique(array_column($data['content'], 'project_id')));
        $tmp = $this->pm->post('/project/projects',['project_ids'=>$project_ids]);
        $projects = $tmp ? many_array_column($tmp['content'], 'project_id') : [];

        $space_type_ids = array_column($data['content'], 'space_type');
        $house_type_ids = array_column($data['content'], 'house_type');
        $tag_ids = array_filter(array_unique(array_merge($space_type_ids,$house_type_ids)));
        $tmp = $this->tag->post('/tag/lists',['tag_ids'=>implode(',',$tag_ids), 'nolevel' => 'Y']);
        $tags = $tmp ? many_array_column($tmp['content'], 'tag_id') : [];

        // 员工
        $employee_ids = array_unique(array_merge(array_filter(array_column($data['content'], 'created_by')), array_filter(array_column($data['content'], 'updated_by'))));
        $employees = $this->user->post('/employee/lists',['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        // 文件
        $file_ids = [];
        foreach ($data['content'] as $item) {
            foreach ($item['space_files'] ?: [] as $v) $file_ids[] = $v['file_id'];
        }
        $files = $this->fileupload->post('/list',['file_ids' => $file_ids]);
        $files = ($files['code'] === 0 && $files['content']) ? many_array_column($files['content'], 'file_id') : [];
        $files = array_map(function ($m){
            $file_attributes = $m['file_attributes'] ? json_decode($m['file_attributes'], true) : [];
            return $file_attributes['name'] ?? '';
        },$files);

        // space
        $space_ids = array_unique(array_filter(array_column($data['content'], 'space_id')));
        $space_branches = $this->pm->post('/space/batchBranch', ['space_ids' => $space_ids]);
        $space_branches = $space_branches['content'] ?? [];

        $lists = array_map(function ($m) use ($projects,$tags,$employees,$files,$space_branches) {
            $m['project_name'] = getArraysOfvalue($projects, $m['project_id'], 'project_name');
            $m['space_type_name'] = getArraysOfvalue($tags, $m['space_type'], 'tag_name');
            $m['house_type_name'] = getArraysOfvalue($tags, $m['house_type'], 'tag_name');
            $m['created_by'] = getArraysOfvalue($employees, $m['created_by'], 'full_name');
            $m['updated_by'] = getArraysOfvalue($employees, $m['updated_by'], 'full_name');
            $m['space_files'] = $m['space_files'] ? array_map(function ($v) use ($files) {
                return [
                    'fileId' => $v['file_id'],
                    'fileName' => $files[$v['file_id']] ?? '',
                ];
            }, $m['space_files']) : [];
            $m['space_name_full'] = '';
            if (isset($space_branches[$m['space_id']])) {
                $tmp_name_arr = array_reverse(array_column($space_branches[$m['space_id']], 'space_name'));
                $tmp_name_arr = array_slice($tmp_name_arr, 0, -1);
                $m['space_name_full'] = implode('-', $tmp_name_arr);
            }
           return $m;
        },$data['content']);

        rsp_success_json(['total' => $num,'lists' => $lists],'查询成功');
    }

    public function add($params = []){
        $info = [];

        if(isTruekey($params,'project_id')){
            $info['project_id'] = $params['project_id'];
        }else{
            rsp_error_tips(10001,'所属项目');
        }

        if(isset($params['space_name'])){
            $info['space_name'] = $params['space_name'];
            if(mb_strlen($info['space_name'],'UTF8') > 25) rsp_error_tips(10007,'空间名称只能输入25个字符');
        }else{
            rsp_error_tips(10001,'空间名称');
        }

        if(isTruekey($params,'space_type')){
            $info['space_type'] = $params['space_type'];
        }else{
            rsp_error_tips(10001,'空间类型');
        }

        $field = [
            'space_id','space_floor','house_type','charge_area','describe','remark','space_files','main_exit_tag_id','outer_id','direction'
        ];

        $info = initParams($params,$field,$info);
        if (isTrueKey($info, 'space_files')) {
            $info['space_files'] = json_encode(array_map(function ($m) {
                return [
                    'file_id' => $m['fileId'],
                ];
            }, json_decode($info['space_files'], true)));
        }
        $info['parent_id'] = $params['parent_id'] ?? '';
        $this->spaceInterceptor($info);

        $space_id = resource_id_generator(self::RESOURCE_TYPES['space']);
        if(!$space_id) rsp_error_tips(10001,'资源ID');
        $info['space_id'] = $space_id;

        $info['created_by'] = $info['updated_by'] = $this->employee_id;
        $info['project_id'] = $this->project_id;
        $result = $this->pm->post('/space/add',$info);

        if($result['code'] !=0 ) rsp_die_json(10005, $result['message']);
        //添加审计日志
        Comm_AuditLogs::push(1340, $info['space_id'], '添加空间', 1323, $info, '成功');

        rsp_success_json($info['space_id'],'添加成功');
    }

    public function update($params = []){
        if(!isTrueKey($params,'space_id')) rsp_error_tips(10001,'space_id');
        $info = [];
        if(isTruekey($params,'project_id')){
            $info['project_id'] = $params['project_id'];
        }else{
            rsp_error_tips(10001,'所属项目');
        }

        if(isset($params['space_name'])){
            $info['space_name'] = $params['space_name'];
            if(mb_strlen($info['space_name'],'UTF8') > 25) rsp_error_tips(10007,'空间名称只能输入25个字符');
        }else{
            rsp_error_tips(10001,'空间名称');
        }

        if(isTruekey($params,'space_type')){
            $info['space_type'] = $params['space_type'];
        }else{
            rsp_error_tips(10001,'空间类型');
        }

        $field = [
            'space_id','space_floor','house_type','charge_area','describe','remark','space_files','main_exit_tag_id','outer_id','direction'
        ];

        $info = initParams($params,$field,$info);
        if (isTrueKey($info, 'space_files')) {
            $info['space_files'] = json_encode(array_map(function ($m) {
                return [
                    'file_id' => $m['fileId'],
                ];
            }, json_decode($info['space_files'], true)));
        }
        $info['parent_id'] = $params['parent_id'] ?? '';
        $this->spaceInterceptor($info);

        unset($info['created_by']);
        $info['updated_by'] = $this->employee_id;
        $info['project_id'] = $this->project_id;

        $this->treeRotate($info);

        $result = $this->pm->post('/space/update',$info);

        Comm_AuditLogs::push(
            1340,
            $info['space_id'],
            '更新空间信息',
            1324,
            $info,
            (!isset($result['code']) || $result['code'] != 0) ? '失败' : '成功'
        );
        if($result['code'] !=0 ) rsp_die_json(10006, $result['message']);


        rsp_success_json($result['content'],'更新成功');
    }

    private function spaceInterceptor(Array $info = [])
    {
        if (!$info['parent_id']) {
            return true;
        }
        if (intval($info['space_type']) === 244) {
            rsp_die_json(10001, '楼栋只能是顶级空间');
        }
        $parent = $this->pm->post('/space/show', ['space_id' => $info['parent_id']]);
        $parent = $parent['content'] ?? [];
        if (!$parent) {
            rsp_die_json(10001, '父级空间不存在');
        }
        return true;
    }

    /**
     * https://bug.eptingche.cn/zentao/story-view-260.html
     * @param array $info
     * @return bool
     */
    private function treeRotate(Array $info = [])
    {
        if (!$info['parent_id']) {
            return true;
        }
        if ($info['space_id'] === $info['parent_id']) {
            rsp_error_tips(10015, '父级空间不能是自身');
        }
        $parent_branch = $this->pm->post('/space/branch', ['space_id' => $info['parent_id']]);
        $parent_branch = $parent_branch['content'] ?? [];
        if (!$parent_branch) {
            rsp_error_tips(10015, '父级空间id');
        }
        // parent_id是顶级空间
        if (count($parent_branch) === 1 && $info['parent_id'] === $parent_branch[0]['space_id']) {
            return true;
        }
        $parent_ids = array_unique(array_filter(array_column($parent_branch, 'space_id')));
        // parent_id不是自身的子空间
        if (!in_array($info['space_id'], $parent_ids)) {
            return true;
        }
        $child_i = 0;
        foreach ($parent_branch as $k => $space) {
            if ($space['space_id'] !== $info['space_id']) {
                continue;
            }
            $child_i = $k -1;
            break;
        }
        $res = $this->pm->post('/space/updateParent', ['space_id' => $parent_branch[$child_i]['space_id'], 'parent_id' => '']);
        if ($res['code'] !== 0) {
            rsp_die_json(10001, '空间树rotate失败');
        }
        return true;
    }

    public function tree($params = [])
    {
        if (!$this->project_id) rsp_success_json([]);
        $params['project_id'] = $this->project_id;
        $data = $this->pm->post('/space/tree', $params);
        if ($data['code'] !== 0) rsp_error_tips(10002);

        // 员工
        $employee_ids = $this->getEmployeeIdsFromTree($data['content']);
        $employees = $this->user->post('/employee/lists',['employee_ids' => $employee_ids]);
        $employees = ($employees['code'] === 0 && $employees['content']) ? many_array_column($employees['content'], 'employee_id') : [];

        // resource lites
        $other_ids = $this->getOtherIdsFromTree($data['content'],['space_id']);
        $resource_lites = [];
        if( $other_ids ){
            $resource_lites = $this->resource->post('/resource/id/lite',['resource_ids' => array_unique(array_filter($other_ids['space_id']))]);
            $resource_lites = ($resource_lites['code'] === 0 && $resource_lites['content']) ? many_array_column($resource_lites['content'], 'resource_id') : [];
        }


        $result = $this->treeRecursive($data['content'], $employees,$resource_lites);
        rsp_success_json(array_values($result));
    }

    private function getOtherIdsFromTree($tree,$columns)
    {
        static $return = [];
        if (!$tree) return [];
        foreach ($tree as $k => $item) {
            foreach ($columns as $v){
                $return[$v][] = $item[$v] ?? '';
            }
            $this->getOtherIdsFromTree($item['children'] ?? [],$columns);
        }
        return $return;
    }

    private function getEmployeeIdsFromTree($tree)
    {
        static $employee_ids = [];
        if (!$tree) return [];
        foreach ($tree as $k => $item) {
            $employee_ids[] = $item['created_by'];
            $employee_ids[] = $item['updated_by'];
            $this->getEmployeeIdsFromTree($item['children'] ?? []);
        }

        return array_values(array_unique(array_filter($employee_ids)));
    }

    private function treeRecursive($tree, $employees,$resource_lites)
    {
        if (!$tree) return [];
        foreach ($tree as $k => $item) {
            $tree[$k]['created_by'] = getArraysOfvalue($employees, $item['created_by'], 'full_name');
            $tree[$k]['updated_by'] = getArraysOfvalue($employees, $item['updated_by'], 'full_name');
            $tree[$k]['resource_lite'] = getArraysOfvalue($resource_lites, $item['space_id'], 'resource_lite');
            $tree[$k]['children'] = $this->treeRecursive($item['children'] ?? [], $employees,$resource_lites);
        }

        return $tree;
    }

    public function names($params = [])
    {
        if (!isTrueKey($params, 'space_ids')) rsp_error_tips(10001);
        $space_ids = is_array($params['space_ids']) ? $params['space_ids'] : json_decode($params['space_ids'], true);
        $names = [];
        foreach ($space_ids as $space_id) {
            $space_names = [];
            $branch = $this->pm->post('/space/branch', ['space_id' => $space_id]);
            $branch = $branch['content'] ?? [];
            usort($branch, function ($a, $b) {
                return ($a['space_type'] <=> $b['space_type']);
            });
            foreach ($branch ?: [] as $item) {
                $space_names[] = $item['space_name'];
            }
            $names[] = implode('-', $space_names);
        }
        rsp_success_json($names);
    }

    public function types($params = [])
    {
        $space_types = $this->tag->post('/tag/lists',['type_id' => 39, 'nolevel' => 'Y']);
        $space_types = $space_types['content'] ?? [];
        if (!$space_types) rsp_success_json([]);
        if (!isTrueKey($params, 'space_type')) rsp_success_json($space_types);
        $filter = "typeFilter{$params['space_type']}";
        if (!method_exists($this, $filter)) rsp_success_json($space_types);
        $space_types = $this->$filter($space_types);
        rsp_success_json($space_types);
    }

    public function typeFilter1392($space_types = [])
    {
        $space_types = many_array_column($space_types, 'tag_id');
        unset($space_types[244]);
        return array_values($space_types);
    }

    public function typeFilter1393($space_types = [])
    {
        $space_types = many_array_column($space_types, 'tag_id');
        unset($space_types[244], $space_types[1392]);
        return array_values($space_types);
    }

    public function typeFilter1394($space_types = [])
    {
        $space_types = many_array_column($space_types, 'tag_id');
        unset($space_types[244], $space_types[1392], $space_types[1393]);
        return array_values($space_types);
    }
}