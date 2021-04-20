<?php

class Frame extends Base {
    
    public function lists($params = []){
        log_message(__METHOD__.'-----'.json_encode($params) );
        $lists = $this->pm->post('/frame/lists', $params);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);
        
        $count = $this->pm->post('/frame/count', $params);
        if($count['code'] !== 0 || !$count['content']) rsp_success_json(['total' => 0, 'lists' => []]);
        rsp_success_json(['total' => $count['content'], 'lists' => $lists['content'] ]);
    }
    
    public function add($params = []){
        log_message(__METHOD__.'-----'.json_encode($params) );
        if(!isset($params['frame_name']) || (String)$params['frame_name'] === '' || !isset($params['frame_parent']) ) rsp_die_json(10001,'缺少架构名称');
        if(mb_strlen($params['frame_name']) > 25) rsp_die_json(10002,'架构名称长度不能超过25个字符');
        
        $frame_id = resource_id_generator(self::RESOURCE_TYPES['frame']);
        if(!$frame_id) rsp_die_json(10003,'添加失败');
        $result = $this->pm->post('/frame/add', [
            'frame_id'  => $frame_id,
            'frame_name' => trim($params['frame_name']),
            'frame_parent' => $params['frame_parent'],
        ]);
        if($result['code'] != 0) rsp_die_json(10004,$result['message']);
        rsp_success_json('');
    }
    
    public function update($params = []){
        log_message(__METHOD__.'-----'.json_encode($params) );
        if(isTrueKey($params,'frame_id') == false) rsp_die_json(10001,'参数缺失');
        if( !isset($params['frame_name']) || (String)$params['frame_name'] === '' ) rsp_die_json(10001,'缺少架构名称');
        if(mb_strlen($params['frame_name']) > 25) rsp_die_json(10002,'架构名称长度不能超过25个字符');
        $result = $this->pm->post('/frame/update', [
            'frame_name' => trim($params['frame_name']),
            'frame_id' => $params['frame_id'],
        ]);
        if($result['code'] != 0) rsp_die_json(10004,$result['message']);
        rsp_success_json('');
    }
    
    public function delete($params = []){
        log_message(__METHOD__.'-----'.json_encode($params) );
        if(isTrueKey($params,'frame_id') == false) rsp_die_json(10001,'参数缺失');
        $frame_show = $this->pm->post('/frame/show', [
            'frame_parent' => $params['frame_id'],
            'is_delete' => 'N',
        ]);
        
        if($frame_show['code'] == 0 && !empty($frame_show['content']) ) rsp_die_json(10003,'该架构存在子级,请勿删除');
        $result = $this->pm->post('/frame/update', [
            'is_delete' => 'Y',
            'frame_id' => $params['frame_id'],
        ]);
        if($result['code'] != 0) rsp_die_json(10004,'删除失败');
        rsp_success_json('');
    }
    
    public function basic_lists($params = []){
        log_message(__METHOD__.'-----'.json_encode($params) );
        $lists = $this->pm->post('/framelists', $params);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);
        
        $count = $this->pm->post('/frame/count', $params);
        if($count['code'] !== 0 || !$count['content']) rsp_success_json(['total' => 0, 'lists' => []]);
        rsp_success_json(['total' => $count['content'], 'lists' => $lists['content'] ]);
    }
    
    public function project_lists($params = []){
        log_message(__METHOD__.'-----'.json_encode($params) );
        $lists = $this->pm->post('/frame/project/lists', $params);
        if ($lists['code'] !== 0 || !$lists['content']) rsp_success_json(['total' => 0, 'lists' => []]);
        
        $count = $this->pm->post('/frame/count', $params);
        if($count['code'] !== 0 || !$count['content']) rsp_success_json(['total' => 0, 'lists' => []]);
        rsp_success_json(['total' => $count['content'], 'lists' => $lists['content'] ]);
    }
}