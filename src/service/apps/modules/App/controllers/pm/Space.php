<?php

final class Space extends Base
{
    public function lists($params = [])
    {
        if (!isTrueKey($params,'project_id')) rsp_error_tips(10001, "project_id");
        $tags = $this->tag->post('/tag/lists',['type_id' => 39, 'parent' => 0]);
        $tags = $tags['content'] ?? [];
        if (!$tags) rsp_success_json(['total' => 0,'lists' => []]);
        $params['space_types'] = array_filter(array_column($tags, 'tag_id'));
        $params['is_paging'] = 'N';
        $data = $this->pm->post('/space/lists', $params);
        if ($data['code'] !== 0) rsp_error_tips(10002);
        if (empty($data['content'])) rsp_success_json(['total' => 0,'lists' => []]);
        $lists = $data['content'];
        rsp_success_json(['total' => count($lists),'lists' => $lists]);
    }

    public function buildings($params = [])
    {
        if (!isTrueKey($params,'project_id')) rsp_error_tips(10001, "project_id");
        $has_house = (isset($params['has_house']) && intval($params['has_house']) === 0) ? 0 : 1;
        $user_id = $params['user_id'] ?? '';
        if ($user_id) {
            $tenements = $this->user->post('/tenement/lists', ['user_ids' => [$user_id], 'tenement_check_status' => 'Y', 'project_id' => $params['project_id']]);
            $tenements = $tenements['content'] ?? [];
            if (!$tenements) {
                info(__METHOD__, ['user_ids' => [$user_id], 'error' => '查询已认证住户失败']);
                rsp_success_json(['total' => 0,'lists' => []]);
            }
            $houses = $this->user->post('/house/lists', ['tenement_ids' => array_unique(array_filter(array_column($tenements,'tenement_id'))), 'tenement_house_status' => 'Y']);
            $houses = $houses['content'] ?? [];
            if (!$houses) {
                info(__METHOD__, ['tenement_ids' => array_unique(array_filter(array_column($tenements,'tenement_id'))), 'error' => '查询住户房产失败']);
                rsp_success_json(['total' => 0,'lists' => []]);
            }
            $houses = $this->pm->post('/house/basic/lists', ['house_ids' => array_unique(array_filter(array_column($houses,'house_id')))]);
            $houses = $houses['content'] ?? [];
            if (!$houses) {
                info(__METHOD__, ['house_ids' => array_unique(array_filter(array_column($houses,'house_id'))), 'error' => '查询房产失败']);
                rsp_success_json(['total' => 0,'lists' => []]);
            }
            $house_spaces = $this->pm->post('/space/lists', ['space_ids' => array_unique(array_filter(array_column($houses,'space_id')))]);
            $house_spaces = $house_spaces['content'] ?? [];
            if (!$house_spaces) {
                info(__METHOD__, ['space_ids' => array_unique(array_filter(array_column($houses,'space_id'))), 'error' => '查询房产空间失败']);
                rsp_success_json(['total' => 0,'lists' => []]);
            }
            $lists = [];
            foreach ($house_spaces as $space) {
                $branch = $this->pm->post('/space/branch', ['space_id' => $space['space_id']]);
                $branch = $branch['content'] ?? [];
                foreach ($branch ?: [] as $item) {
                    if ($item['space_type'] === 244 && !in_array($item, $lists)) $lists[] = $item;
                }

            }
            rsp_success_json(['total' => count($lists),'lists' => $lists]);
        }

        if ($has_house) $params = array_merge($params, ['has_house' => 1]);
        $data = $this->pm->post('/space/buildings', $params);
        if ($data['code'] !== 0) rsp_error_tips(10002);
        if (empty($data['content'])) rsp_success_json(['total' => 0,'lists' => []]);
        $lists = $data['content'];
        rsp_success_json(['total' => count($lists),'lists' => $lists]);
    }

    public function houses($params = [])
    {
        if (!isTrueKey($params, 'space_id')) rsp_success_json([]);
        $show = $this->pm->post('/space/show', $params);
        $show = $show['content'] ?? [];
        if (!$show) rsp_die_json(10001, '楼栋不存在');
        if ($show['space_type'] !== 244) rsp_die_json(10001, '该空间不是楼栋');

        $data = $this->pm->post('/space/children', $params);
        if ($data['code'] !== 0) rsp_error_tips(10002);
        if (!isTrueKey($data, 'content')) rsp_success_json([]);
        $spaces = many_array_column($data['content'], 'space_id');
        $houses = [];
        foreach ($spaces ?: [] as $space) {
            if ($space['space_type'] === 1394) $houses[] = $space;
        }
        if (!$houses) rsp_success_json([]);
        $project_id = $houses[0]['project_id'];

        $user_id = $params['user_id'] ?? '';
        if ($user_id) {
            $tenements = $this->user->post('/tenement/lists', ['user_ids' => [$user_id], 'tenement_check_status' => 'Y', 'project_id' => $project_id]);
            $tenements = $tenements['content'] ?? [];
            if (!$tenements) {
                info(__METHOD__, ['user_ids' => [$user_id], 'error' => '查询已认证住户失败']);
                rsp_success_json(['total' => 0,'lists' => []]);
            }
            $user_houses = $this->user->post('/house/lists', ['tenement_ids' => array_unique(array_filter(array_column($tenements,'tenement_id'))), 'tenement_house_status' => 'Y']);
            $user_houses = $user_houses['content'] ?? [];
            if (!$user_houses) {
                info(__METHOD__, ['tenement_ids' => array_unique(array_filter(array_column($tenements,'tenement_id'))), 'error' => '查询住户房产失败']);
                rsp_success_json(['total' => 0,'lists' => []]);
            }
            $user_houses = $this->pm->post('/house/basic/lists', ['house_ids' => array_unique(array_filter(array_column($user_houses,'house_id')))]);
            $user_houses = $user_houses['content'] ?? [];
            if (!$user_houses) {
                info(__METHOD__, ['house_ids' => array_unique(array_filter(array_column($user_houses,'house_id'))), 'error' => '查询房产失败']);
                rsp_success_json(['total' => 0,'lists' => []]);
            }
            $user_house_space_ids = array_unique(array_filter(array_column($user_houses, 'space_id')));
            $houses = array_values(array_filter(array_map(function ($m) use ($user_house_space_ids) {
                return in_array($m['space_id'], $user_house_space_ids) ? $m : null;
            }, $houses)));
        }

        $house_list = $this->pm->post('/house/basic/lists', ['space_ids' => array_unique(array_filter(array_column($houses,'space_id')))]);
        $house_list = $house_list['content'] ?? [];
        if (!$house_list) {
            info(__METHOD__, ['space_ids' => array_unique(array_filter(array_column($houses,'space_id'))), 'error' => '查询房产失败']);
            rsp_success_json([]);
        }
        $house_list = many_array_column($house_list, 'space_id');

        $houses = array_map(function ($m) use ($spaces, $house_list) {
            $m['house_id'] = getArraysOfvalue($house_list, $m['space_id'], 'house_id');
            if (!$m['house_id']) return null;
            $m['space_id_building'] = '';
            $m['space_name_building'] = '';
            $m['space_name_exclude_building'] = $m['space_name'];
            $pid = $m['parent_id'];
            do {
                $parent = $spaces[$pid];
                if ($parent['space_type'] === 244) {
                    $m['space_id_building'] = $parent['space_id'];
                    $m['space_name_building'] = $parent['space_name'];
                }
                if (in_array($parent['space_type'], [1392, 1393, 1394])) $m['space_name_exclude_building'] = implode('-', [$parent['space_name'], $m['space_name_exclude_building']]);
                $pid = $parent['parent_id'];
            } while($pid);
            return $m;
        }, $houses);
        $houses = array_values(array_filter($houses));
        rsp_success_json($houses);
    }

}