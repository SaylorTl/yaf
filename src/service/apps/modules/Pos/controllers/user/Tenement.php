<?php

final class Tenement extends Base
{

    /**
     * @param array $post
     * @throws Exception
     * 业主列表
     */
    public function TenementList($post=[])
    {
        $houseParams = [];
        if (isTrueKey($post, 'space_id')) {
            $space_ids = $this->pm->post('/space/children', ['space_id' => $post['space_id']]);
            $space_ids = ($space_ids['code'] === 0 && $space_ids['content']) ? $space_ids['content'] : [];
            if (!$space_ids) rsp_success_json(['total' => 0, 'lists' => []]);
            unset($post['space_id']);
            $houseParams['space_ids'] = array_unique(array_filter(array_column($space_ids, 'space_id')));
        }
        if (isset($post['house_id'])) {
            $houseParams['house_id'] = $post['house_id'];
            unset($post['house_id']);
        }
        if(!empty($houseParams)){
            $houseRes = $this->pm->post('/house/lists',$houseParams);
            if ($houseRes['code'] !== 0){
                rsp_die_json(10002,$houseRes['message']);
            }
            if (empty($houseRes['content'])){
                rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
            }
            $house_ids = array_column($houseRes['content'],'house_id');
            $post['house_ids'] = $house_ids;
        }
        $cellParams = [];
        if(!empty($post['cell_name'])){
            $cellParams['cell_name'] = $post['cell_name'];
            unset($post['cell_name']);
            if(!empty($house_ids)){
                $cellParams['house_ids'] = $house_ids;
            }
            $cellRes = $this->pm->post('/house/cells/lists',$cellParams);
            if ($cellRes['code'] !== 0){
                rsp_die_json(10002,$cellRes['message']);
            }
            if (empty($cellRes['content'])){
                rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
            }
            $cell_ids = array_column($cellRes['content'],'cell_id');
            $post['cell_ids'] = $cell_ids;
        }
        $result = $this->user->post('/tenement/userlist',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }
        $lists = $result['content']['lists'];
        $out_reason_arr = array_column($lists,'out_reason_tag_id');
        $rescue_type_arr = array_column($lists,'rescue_type_tag_id');
        $pet_type_arr = array_column($lists,'pet_type_tag_id');
        $tenement_type_arr = array_column($lists,'tenement_type_tag_id');
        $license_tag_arr = array_column($lists,'license_tag_id');
        $customer_type_arr = array_column($lists,'customer_type_tag_id');
        $car_type__arr = array_column($lists,'car_type_tag_id');
        $sex_arr = array_column($lists,'sex');
        $pet_type_arr = implode(',',$pet_type_arr);
        $pet_type_arr = array_unique(explode(',',$pet_type_arr));
        $tags = array_filter(array_merge($out_reason_arr,$rescue_type_arr,$pet_type_arr,$tenement_type_arr,
            $car_type__arr,$sex_arr,$license_tag_arr,$customer_type_arr));
        $tag_res = $this->tag->post('/tag/lists',['tag_ids'=>implode(',',$tags)]);
        if($tag_res['code']!=0){
            rsp_die_json(10002,$tag_res['message']);
        }
        $tag_content = array_column($tag_res['content'],null,'tag_id');
        $project_arr = array_unique(array_filter(array_column($lists,'project_id')));
        $project_res = $this->pm->post('/project/lists',['project_ids'=>$project_arr]);
        if($project_res['code']!=0){
            rsp_die_json(10002,$project_res['message']);
        }
        $creator_arr = array_filter(array_unique(array_column($lists,'creator')));
        $editor_arr = array_filter(array_unique(array_column($lists,'editor')));
        $creator_res = $this->user->post('/employee/lists',['employee_ids'=>array_merge($creator_arr,$editor_arr)]);
        if($creator_res['code']!=0){
            rsp_die_json(10002,$creator_res['message']);
        }
        $creator_content =  array_column($creator_res['content'],null,'employee_id');

        $project_content = array_column($project_res['content'],null,'project_id');
        foreach($result['content']['lists'] as $key=>$value){
            $result['content']['lists'][$key]['out_reason_name'] = isset($tag_content[$value['out_reason_tag_id']])?$tag_content[$value['out_reason_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['rescue_type_name'] = isset($tag_content[$value['rescue_type_tag_id']])?$tag_content[$value['rescue_type_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['pet_type_name'] = isset($tag_content[$value['pet_type_tag_id']])?$tag_content[$value['pet_type_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['customer_type_name'] = isset($tag_content[$value['customer_type_tag_id']])?$tag_content[$value['customer_type_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['license_tag_name'] = isset($tag_content[$value['license_tag_id']])?$tag_content[$value['license_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['tenement_type_name'] =isset($tag_content[$value['tenement_type_tag_id']])?$tag_content[$value['tenement_type_tag_id']]['tag_name']:'';
            $result['content']['lists'][$key]['project_name'] =isset($project_content[$value['project_id']])?$project_content[$value['project_id']]['project_name']:'';
            $result['content']['lists'][$key]['in_time'] = !empty($value['in_time'])?date('Y-m-d H:i:s',$value['in_time']):'';
            $result['content']['lists'][$key]['out_time'] = !empty($value['out_time'])?date('Y-m-d H:i:s',$value['out_time']):'';
            $result['content']['lists'][$key]['creator'] = isset($creator_content[$value['creator']])?$creator_content[$value['creator']]['full_name']:'';
            $result['content']['lists'][$key]['editor'] = isset($creator_content[$value['editor']])?$creator_content[$value['editor']]['full_name']:'';
            $pet_str = [];
            if(!empty($value['pet_type_tag_id'])){
                $pet_tags = explode(',',$value['pet_type_tag_id']);
                foreach($pet_tags as $vl){
                    $pet_str[]= isset($tag_content[$vl])?$tag_content[$vl]['tag_name']:'';
                }
            }
            $result['content']['lists'][$key]['pet_str'] = implode(',',$pet_str);
        }
        rsp_success_json($result['content'],'查询成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 业主添加
     */
    public function TenementAdd($post=[])
    {
        if(empty($post['tenement_type_tag_id'])){
            rsp_die_json(10001, '住户类型不能为空');
        }
        if(empty($post['mobile']) ){
            rsp_die_json(10001,'手机号码缺失');
        }
        if($post['tenement_type_tag_id']== '411'){
            $check_params_info = checkParams($post, ['project_id','real_name','sex','mobile','in_time',
                'license_tag_id','license_num','customer_type_tag_id','birth_day','tenement_type_tag_id']);
        }else{
            $check_params_info = checkParams($post, ['project_id','tenement_type_tag_id','real_name',
                'license_tag_id','license_num','contact_name','email','mobile','in_time']);
        }
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }
        $house_lists = [];
        if (isTrueKey($post, 'house_list')) {
            $house_lists = array_map(function ($m) {
                if (!isTrueKey($m, 'space_id')) {
                    rsp_die_json(10001, '请选择房屋');
                }
                $houses = $this->pm->post('/house/basic/lists', ['space_id' => $m['space_id']]);
                $houses = $houses['content'] ?? [];
                if (!$houses) {
                    rsp_die_json(10001, '房屋不存在');
                }
                if (count($houses) > 1) {
                    rsp_die_json(10001, '必须选择具体的房屋');
                }
                $m['house_id'] = $houses[0]['house_id'];
                return $m;
            }, $post['house_list']);
        }

        $tenement_params = ['project_id'=>$post['project_id'],'mobile'=>$post['mobile'],'page'=>1,'pagesize'=>1 ];
        if(isTrueKey($_SESSION,'user_id')){
            $tenement_params['user_id'] = $_SESSION['user_id'];
            unset($tenement_params['mobile']);
        }
        $result = $this->user->post('/tenement/lists',$tenement_params);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }

        if(empty($result['content'])){
            //添加新住户
            $tenement_res = $this->resource->post('/resource/id/generator',['type_name'=>'tenement']);
            if($tenement_res['code']!=0 || ($tenement_res['code']==0 && empty($tenement_res['content']))){
                rsp_die_json(10001, '资源id生成失败');
            }
            $tenement_id = $tenement_res['content'];
            $post['tenement_id'] = $tenement_res['content'];
            $post['creator'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
            $post['editor'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
            if(isset($post['house_list'])) unset($post['house_list']);
            $useradd = $this->user->post('/tenement/useradd',$post);
            if($useradd['code']!=0 ){
                rsp_die_json(10002,$useradd['message']);
            }
        }else{
            $tenement_id = $result['content'][0]['tenement_id'];
        }

        if( $house_lists){
            //绑定房子
            //根据住户id 房间号id 查询是否已经绑定过
            $house = $house_lists[0];
            $house_show = $this->user->post('/house/lists',[
                'tenement_id'=>$tenement_id,
                'house_id'=>$house['house_id'],
                'page'=>1,
                'pagesize'=>1
            ]);

            if($house_show['code'] != 0) {
                rsp_die_json(10002,$house_show['message']);
            }

            if(!empty($house_show['content']) ){
                rsp_die_json(10002,'该房子已存在，请勿重复添加');
            }

            $house_add_params = [
                'tenement_id'=>$tenement_id,
                'house_id'=>$house['house_id'],
            ];
            if(isTrueKey($house,'tenement_house_status') ){
                $house_add_params['tenement_house_status'] = $house['tenement_house_status'];
            }
            if(isTrueKey($house,'tenement_identify_tag_id') ){
                $house_add_params['tenement_identify_tag_id'] = $house['tenement_identify_tag_id'];
            }
            if(isTrueKey($house,'in_time') ){
                $house_add_params['in_time'] = strtotime($house['in_time']);
            }
            $result = $this->user->post('/house/add',$house_add_params);
            if($result['code'] != 0 ){
                rsp_die_json(10002,$result['message']);
            }

        }
        rsp_success_json([],'添加成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 附加信息展示
     */
    public function Tenementextlist($post=[])
    {
        $result = $this->user->post('/tenement/extlist',$post);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json(['car_list'=>[],'house_list'=>[],'label_list'=>[]],'查询成功');
        }
        if(!empty($result['content']['car_list'])){
            $car_type_arr = array_column($result['content']['car_list'],'car_type_tag_id');
            $tags = array_unique(array_filter($car_type_arr));
            $tag_res = $this->tag->post('/tag/lists',['tag_ids'=>implode(',',$tags)]);
            if($tag_res['code']!=0){
                rsp_die_json(10002,$tag_res['message']);
            }
            $tag_content = array_column($tag_res['content'],null,'tag_id');
            if(!empty($result['content']['car_list'])){
                foreach($result['content']['car_list'] as $key=>$value){
                    $result['content']['car_list'][$key]['car_type_tag_name'] = isset($tag_content[$value['car_type_tag_id']])?$tag_content[$value['car_type_tag_id']]['tag_name']:'';
                }
            }
        }
        if(!empty($result['content']['label_list'])){
            $tenement_tag_arr = array_column($result['content']['label_list'],'tenement_tag_id');
            $tags = array_unique(array_filter($tenement_tag_arr));
            $tag_res = $this->tag->post('/tag/lists',['tag_ids'=>implode(',',$tags)]);
            if($tag_res['code']!=0 ){
                rsp_die_json(10002,$tag_res['message']);
            }
            $tag_content = array_column($tag_res['content'],null,'tag_id');
            if(!empty($result['content']['label_list'])){
                foreach($result['content']['label_list'] as $key=>$value){
                    $result['content']['label_list'][$key]['tenement_tag_name'] = isset($tag_content[$value['tenement_tag_id']])?$tag_content[$value['tenement_tag_id']]['tag_name']:'';
                }
            }
        }
        if(!empty($result['content']['house_list'])){
            $house_content = array_unique(array_filter(array_column($result['content']['house_list'],'house_id')));
            if(!empty($house_content)){
                $house_arr =  $this->pm->post('/house/lists',['house_ids'=>$house_content]);
                $house_res = array_column($house_arr['content'],null,'house_id');
            }else{
                $house_res = [];
            }

            $cell_content = array_unique(array_filter(array_column($result['content']['house_list'],'cell_id')));
            if(!empty($cell_content)){
                $cell_arr =  $this->pm->post('/house/cells/lists',['cell_ids'=>$cell_content]);
                $cell_res = array_column($cell_arr['content'],null,'cell_id');
            }else{
                $cell_res = [];
            }
            foreach($result['content']['house_list'] as $key=>$value){
                $result['content']['house_list'][$key]['space_id'] = isset($house_res[$value['house_id']])?$house_res[$value['house_id']]['space_id']:'';
                $result['content']['house_list'][$key]['cell_name'] = isset($cell_res[$value['cell_id']])?$cell_res[$value['cell_id']]['cell_name']:'';
                $result['content']['house_list'][$key]['in_time'] = $value['in_time'] ? date('Y-m-d H:i:s',$value['in_time']):'';
                $result['content']['house_list'][$key]['out_time'] = $value['out_time'] ? date('Y-m-d H:i:s',$value['out_time']):'';
            }
        }
        rsp_success_json($result['content'],'查询成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 业主添加
     */
    public function TenementUpdate($post=[])
    {
        if(empty($post['tenement_id'])){
            rsp_die_json(10001, '缺少住户id');
        }
        if(empty($post['tenement_type_tag_id'])){
            rsp_die_json(10001, '住户类型不能为空');
        }

        $check_params_info = checkParams($post, ['tenement_type_tag_id','real_name',
            'license_tag_id','license_num','mobile','sex']);
        if ($check_params_info === false) {
            rsp_die_json(10001, '参数的数据类型错误');
        }
        if (is_array($check_params_info)) {
            rsp_die_json(10001, implode('、', $check_params_info) . '参数缺失');
        }

        $info = ['real_name'=>$post['real_name'],'mobile'=>$post['mobile'],
            'sex'=>$post['sex'], 'license_tag_id'=>$post['license_tag_id'],'license_num'=>$post['license_num'],
            'tenement_type_tag_id'=>$post['tenement_type_tag_id'],'tenement_id'=>$post['tenement_id'],
            'tenement_identify_tag_id'=>$post['tenement_identify_tag_id']
        ];

        if(isTrueKey($post,'in_time')) $info['in_time'] = $post['in_time'];
        if(isTrueKey($post,'out_time')) $info['out_time'] = $post['out_time'];

        $post['editor'] = !empty($_SESSION['employee_id'])?$_SESSION['employee_id']:'';
        $result = $this->user->post('/tenement/update',$info);
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }

        if(isTrueKey($post,'house_list') ){
            $post['house_list'] = array_map(function ($m) {
                if (!isTrueKey($m, 'space_id')) {
                    rsp_die_json(10001, '请选择房屋');
                }
                $houses = $this->pm->post('/house/basic/lists', ['space_id' => $m['space_id']]);
                $houses = $houses['content'] ?? [];
                if (!$houses) {
                    rsp_die_json(10001, '房屋不存在');
                }
                if (count($houses) > 1) {
                    rsp_die_json(10001, '必须选择具体的房屋');
                }
                $m['house_id'] = $houses[0]['house_id'];
                return $m;
            }, $post['house_list']);
            $house = $post['house_list'][0];
            if(isTrueKey($house,'house_id','tenement_id','t_house_id') == false){
                rsp_die_json(10002,'house_list参数缺失');
            }
            $house_update = ['t_house_id'=>$house['t_house_id'],'house_id'=>$house['house_id'],'tenement_id'=>$house['tenement_id']];
            if(isTrueKey($house,'in_time')) $house_update['in_time'] = strtotime($house['in_time']);
            if(isTrueKey($house,'out_time')) $house_update['out_time'] = strtotime($house['out_time']);
            if(isTrueKey($house,'tenement_identify_tag_id')) $house_update['tenement_identify_tag_id'] = $house['tenement_identify_tag_id'];
            if(isTrueKey($house,'tenement_house_status')) $house_update['tenement_house_status'] = $house['tenement_house_status'];
            $this->user->post('/house/update',$house_update);
        }

        rsp_success_json($result['content'],'更新成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 业主信息查看
     */
    public function TenementShow($post=[]){
        $params = ['page'=>1,'pagesize'=>1];
        $result = $this->user->post('/tenement/userlist',array_merge($post,$params) );
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }
        $show = $result['content']['lists'][0];
        //业主详情增加自动更改二维码颜色字段
        $show['auto_qrcode_color'] = $this->_qrcode_color();
        $project_res = $this->pm->post('/project/lists',['project_ids'=>[$show['project_id']] ]);
        if($project_res['code']!=0){
            rsp_die_json(10002,$project_res['message']);
        }
        if(empty($project_res['content'])) {
            rsp_die_json(10002,'项目不存在');
        }
        $show['project_name'] = $project_res['content'][0]['project_name'];
        $tenement_id = $show['tenement_id'];
        $extlist = $this->user->post('/tenement/extlist',['tenement_id'=>$tenement_id]);
        if($extlist['code']!=0 ){
            rsp_die_json(10002,$extlist['message']);
        }
        if( ($extlist['code']==0 &&  empty($extlist['content']) ) || empty($extlist['content']['house_list']) ){
            $show['house_list'] = [];
            rsp_success_json(['lists'=>[$show],'count'=>1],'查询成功');
        }
        if(!empty($extlist['content']['house_list'])){
            $house_content = array_unique(array_filter(array_column($extlist['content']['house_list'],'house_id')));
            if(!empty($house_content)){
                $house_arr =  $this->pm->post('/house/lists',['house_ids'=>$house_content]);
                $house_res = array_column($house_arr['content'],null,'house_id');
            }else{
                $house_res = [];
            }

            $cell_content = array_unique(array_filter(array_column($extlist['content']['house_list'],'cell_id')));
            if(!empty($cell_content)){
                $cell_arr =  $this->pm->post('/house/cells/lists',['cell_ids'=>$cell_content]);
                $cell_res = array_column($cell_arr['content'],null,'cell_id');
            }else{
                $cell_res = [];
            }
            foreach($extlist['content']['house_list'] as $key=>$value){
                $extlist['content']['house_list'][$key]['space_id'] = isset($house_res[$value['house_id']])?$house_res[$value['house_id']]['space_id']:'';
                $extlist['content']['house_list'][$key]['space_name'] = isset($house_res[$value['house_id']])?$house_res[$value['house_id']]['space_name']:'';
                $extlist['content']['house_list'][$key]['house_floor'] = isset($house_res[$value['house_id']])?$house_res[$value['house_id']]['house_floor']:'';
                $extlist['content']['house_list'][$key]['house_unit'] = isset($house_res[$value['house_id']])?$house_res[$value['house_id']]['house_unit']:'';
                $extlist['content']['house_list'][$key]['house_room'] = isset($house_res[$value['house_id']])?$house_res[$value['house_id']]['house_room']:'';
                $extlist['content']['house_list'][$key]['cell_name'] = isset($cell_res[$value['cell_id']])?$cell_res[$value['cell_id']]['cell_name']:'';
            }
            $show['house_list'] = $extlist['content']['house_list'];
        }
        rsp_success_json(['lists'=>[$show],'count'=>1],'查询成功');
    }

    /**
     * @param array $post
     * @throws Exception
     * 根据用户id | 手机号码 查询所有房产信息
     */
    public function tenement_house_lists($post=[]){
        if(empty($post['user_id'])){
            rsp_die_json(10001, '用户信息缺失');
        }
        if(empty($post['mobile'])){
           rsp_die_json(10001, '手机号码缺失'); 
        }
        $result = $this->user->post('/tenement/lists',$post );
        if($result['code']!=0 ){
            rsp_die_json(10002,$result['message']);
        }
        if($result['code']==0 &&  empty($result['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }
        $tenement_ids = array_unique(array_column($result['content'],'tenement_id') );
        $data = $this->user->post('/house/lists',['tenement_ids'=>$tenement_ids ]);
        if($data['code'] != 0){
            rsp_die_json(10002,$data['message']);
        }
        if(empty($data['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }
        $house_ids = array_unique(array_column($data['content'], 'house_id'));
        $houses = $this->pm->post('/house/lists',['house_ids'=>$house_ids]);
        if($houses['code'] != 0){
            rsp_die_json(10002,$houses['message']);
        }
        $houses = many_array_column($houses['content'],'house_id');
        $house_lists = array_map(function($m)use($houses){
            $m['project_id'] = getArraysOfvalue($houses,$m['house_id'],'project_id');
            $m['project_name'] = getArraysOfvalue($houses,$m['house_id'],'project_name');
            $m['space_id'] = getArraysOfvalue($houses,$m['house_id'],'space_id');
            $m['space_name'] = getArraysOfvalue($houses,$m['house_id'],'space_name');
            $m['house_floor'] = getArraysOfvalue($houses,$m['house_id'],'house_floor');
            $m['house_unit'] = getArraysOfvalue($houses,$m['house_id'],'house_unit');
            $m['house_room'] = getArraysOfvalue($houses,$m['house_id'],'house_room');
            return $m;
        },$data['content']);
        rsp_success_json(['lists'=>$house_lists,'count'=>count($house_lists)],'查询成功');
    }

    public function house_lists($post=[]){

        $data = $this->user->post('/house/lists',$post);
        if($data['code'] != 0){
            rsp_die_json(10002,$data['message']);
        }
        if(empty($data['content'])){
            rsp_success_json(['lists'=>[],'count'=>0],'查询成功');
        }

        $count = $this->user->post('/house/count',$post);

        $lists = array_map(function($m)use($data){
            $m['in_time'] = $m['in_time'] ? date('Y-m-d H:i:s',$m['in_time']) : '';
            $m['out_time'] = $m['out_time'] ? date('Y-m-d H:i:s',$m['out_time']) : '';
            return $m;
        },$data['content']);
        rsp_success_json(['count'=>(int)$count['content'],'lists'=>$lists],'查询成功');
    }

    private function _qrcode_color(){
        $config = getConfig('other.ini');
        $qrcode_color= $config->get('tenement.qrcode_color') ?: 'green';
        $color_index = ['green','yellow','blue','red','orange','indigo','violet','black','rand'];
        if( !in_array($qrcode_color,$color_index) ) $qrcode_color = 'green';
        //如果是rand随机
        if($qrcode_color =='rand'){
            $r = rand(0,8);
            $qrcode_color = $color_index[$r];
        }
        $color_box = [
            'green' => [
                'R'=>0,
                'G'=>128,
                'B'=>0
            ],
            'yellow'=>[
                'R'=>255,
                'G'=>255,
                'B'=>0
            ],
            'blue' =>[
                'R'=>0,
                'G'=>0,
                'B'=>255,
            ],
            'red' =>[
                'R'=>255,
                'G'=>0,
                'B'=>0,
            ],
            'orange' =>[
                'R'=>255,
                'G'=>165,
                'B'=>0,
            ],
            'indigo' =>[
                'R'=>75,
                'G'=>0,
                'B'=>130,
            ],
            'violet' =>[
                'R'=>128,
                'G'=>0,
                'B'=>128,
            ],
            'black' =>[
                'R'=>0,
                'G'=>0,
                'B'=>0,
            ]
        ];
        return $color_box[$qrcode_color];
    }
}

