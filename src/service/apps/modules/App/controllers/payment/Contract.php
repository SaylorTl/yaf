<?php

use Project\SpaceModel;

final class Contract extends Base
{
    public function lists($params = [])
    {
        unsetEmptyParams($params);
        $post['car_id'] = encode_plate($params['plate']);
        $post['contract_status_tag_id'] = 1555;
        $post['rule_type_tag_id'] = 1509;
        $contract_show_res = $this->contract->post('/contract/joinList',$post);
        if(0 != $contract_show_res['code']){
            rsp_die_json(10001,$contract_show_res['message']);
        }
        $project_arr = array_unique(array_filter(array_column($contract_show_res['content'],'project_id')));
        $project_res = $this->pm->post('/project/lists',['project_ids'=>$project_arr]);
        if($project_res['code']!=0){
            rsp_die_json(10002,$project_res['message']);
        }
        $project_content = array_column($project_res['content'],null,'project_id');

        $creator_arr = array_filter(array_unique(array_column($contract_show_res['content'],'creator')));
        $editor_arr = array_filter(array_unique(array_column($contract_show_res['content'],'editor')));
        $creator_res = $this->user->post('/employee/lists',['employee_ids'=>array_merge($creator_arr,$editor_arr)]);
        if($creator_res['code']!=0){
            rsp_die_json(10002,$creator_res['message']);
        }
        $creator_content =  array_column($creator_res['content'],null,'employee_id');

        $house_content = array_unique(array_filter(array_column($contract_show_res['content'],'house_id')));
        if(!empty($house_content)){
            $house_arr =  $this->pm->post('/house/lists',['house_ids'=>$house_content]);
            $house_res = array_column($house_arr['content'],null,'house_id');
        }

        $space_ids = array_unique(array_filter(array_column($contract_show_res['content'],'space_id')));
        $space_branches = $this->pm->post('/space/batchBranch', ['space_ids' => $space_ids]);
        $space_branches = $space_branches['content'] ?? [];

        $user_ids = array_filter(array_unique(array_column($contract_show_res['content'],'user_id')));
        $user_Res = $this->user->post('/tenement/userlist',['project_id'=>$_SESSION['member_project_id'],'user_ids'=>$user_ids]);
        if($user_Res['code']!=0 ){
            rsp_die_json(10002,$user_Res['message']);
        }
        $user_content = !empty($user_Res['content'])?array_column($user_Res['content']['lists'],null,'user_id'):[];
        foreach($contract_show_res['content'] as $key=>$value){
            $contract_show_res['content'][$key]['project_name'] =isset($project_content[$value['project_id']])?$project_content[$value['project_id']]['project_name']:'';
            $contract_show_res['content'][$key]['end_time'] = date('Y-m-d',$value['end_time']);
            $contract_show_res['content'][$key]['begin_time'] = date('Y-m-d',$value['begin_time']);
            $contract_show_res['content'][$key]['creator'] = isset($creator_content[$value['creator']])?$creator_content[$value['creator']]['full_name']:'';
            $contract_show_res['content'][$key]['editor'] = isset($creator_content[$value['editor']])?$creator_content[$value['editor']]['full_name']:'';
            $contract_show_res['content'][$key]['real_name'] = isset($value['house_id'])&& !empty($user_content[$value['user_id']])?$user_content[$value['user_id']]['real_name']:$value['real_name'];
            $contract_show_res['content'][$key]['mobile'] = isset($value['house_id'])&& !empty($user_content[$value['user_id']])?$user_content[$value['user_id']]['mobile']:$value['mobile'];
            $contract_show_res['content'][$key]['space_id'] = isset($house_res[$value['house_id']]) ? $house_res[$value['house_id']]['space_id'] : $value['space_id'];
            $branch_info = SpaceModel::parseBranch($space_branches[$value['space_id']] ?? []);
            $contract_show_res['content'][$key]['space_name_full'] = $branch_info['space_name_full'] ?? '';
        }
        rsp_success_json([$contract_show_res['content']],'查询成功');
    }

    public function fee($params = [])
    {
        $place_res = $this->contract->post('/contractplace/lists',['contract_id'=>$params['contract_id']]);
        $space_ids = array_filter(array_unique(array_column($place_res['content'],'space_id')));
        $billing_res = $this->billing->post('/businessConfig/lists', ['space_ids'=>$space_ids,'billing_account_tag_id'=>'1599','status_tag_id'=>'1381','project_id'=>$_SESSION['member_project_id']]);
        if($billing_res['code']!=0  || ($billing_res['code']==0 && empty($billing_res['content'])  )){
            rsp_die_json(10002,"规则查询失败 ");
        }
        $rule_arr = array_column($billing_res['content'],'rule_id');
        $rule_url = getConfig('ms.ini')->get('rule.url');
        $Price = 0;
        $TotalFee = 0 ;
        foreach ($rule_arr as $k=>$v){
            $fee_res = curl_json("post", $rule_url.'/decision/exec', json_encode(['_id'=>$v, "facts"=>["Quantity"=> $params['month_num']]]),["Content-Type:application/json","Oauth-App-Id: uNoilxyVl7fO0uMKKqCP"]);
            if($fee_res['code']!=0  || ($fee_res['code']==0 && empty($fee_res['content'])  )){
                rsp_die_json(10002,"计费配置查询失败 ");
            }
            $Price+= $fee_res['content']['Price'];
            $TotalFee+= $fee_res['content']['TotalFee'];
        }
        rsp_success_json(['price'=>$Price,'total_fee'=>$TotalFee],'查询成功');
    }

}