<?php
/**
 * Created by PhpStorm.
 * User: wuli
 * Date: 2020/12/30
 * Time: 10:07
 */

namespace Charging\App\Zhsq;


use Charging\App\CommonModel;
use Project\SpaceModel;

class MonthModel extends BasicModel implements CommonModel
{
    /**
     * @return mixed|void
     * rule_type_tag_id(业务类型标签id)  1509：月卡  1510：临停
     * contract_status_tag_id（月卡状态tagid） 1555：使用中  1556：已取消
     */
    public function cost()
    {
        if (!isTrueKey($this->request, 'plate', 'month_total')) {
            return returnCode(10001, '请求信息不全');
        }

        $plate = $this->request['plate'];
        $car_info = $this->car->post('/id', ['plate' => $plate]);
        $car_id = $car_info['content'] ?? 0;
        if (empty($car_id)) {
            return returnCode(10001, '获取不到车辆信息');
        }

        // 查询月卡信息
        $show = [
            'car_id' => $car_id,
            'contract_status_tag_id' => 1555,
            'rule_type_tag_id' => 1509
        ];
        if (isTrueKey($this->request, 'contract_id')) {
            $show['contract_id'] = $this->request['contract_id'];
        }
        $contract_info = $this->contract->post('/contractdetail/lists', $show);
        $contract = $contract_info['content'] ?? [];
        if (empty($contract)) {
            info(__METHOD__, ['tip' => '月卡车辆:' . $plate, 'result' => $contract_info]);
            return returnCode(10001, '该车牌没有月卡信息');
        }

        // 查询项目配置信息
        $project_ids = array_unique(array_column($contract, 'project_id'));
        $station_cfg = $this->pm->post('/project/stationcfg/lists', [
            'project_ids' => $project_ids,
            'manage_type' => 1523, // 运营中
            'platform_type' => 1525 //智慧社区
        ]);
        $station_cfg = $station_cfg['content'] ?? [];
        if (empty($station_cfg)) {
            info(__METHOD__, ['tip' => '项目配置:' . $plate, 'project' => $project_ids, 'result' => $station_cfg]);
            return returnCode(10002, '该车牌所属项目未上线');
        }

        $one_contract = [];
        $valid_projects = array_column($station_cfg, 'project_id');
        array_map(function ($m) use (&$one_contract, $valid_projects) {
            if (empty($one_contract) && in_array($m['project_id'], $valid_projects)) {
                $one_contract = $m;
            }
        }, $contract);

        // 查询车辆对应的计费规则，查车辆关联车位空间的计费规则
        if (empty($one_contract['place_id'])) {
            return returnCode(10002, '计费失败，未配置车位信息');
        }
        $parking_info = $this->pm->post('/parkplace/lists', ['place_ids' => [$one_contract['place_id']]]);
        $parking = $parking_info['content'] ?? [];
        $place_space_ids = array_unique(array_column(($parking ?: []), 'space_id'));
        if (empty($place_space_ids) || count($place_space_ids) > 1) {
            info(__METHOD__, ['tip' => '车位:' . $plate, 'contract' => $one_contract, 'result' => $parking_info]);
            return returnCode(10002, '计费失败，相关车位信息配置异常');
        }

        $tag = $this->tag->post('/tag/show', ['tag_id' => 1509]);
        $cost_account_id = $tag['content']['tag_val'] ?? 1599;
        $rule_data = [
            'space_id' => $place_space_ids[0],
            'billing_account_tag_id' => $cost_account_id,
            'status_tag_id' => 1381,
            'project_id' => $one_contract['project_id']
        ];
        $rule_cost = $this->cost->post('/businessConfig/getRule', $rule_data);
        info(__METHOD__, ['tip' => '计费规则-' . $plate, 'data' => $rule_data, 'result' => $rule_cost]);
        $rule_sid = $rule_cost['content']['rule_id'] ?? 0;
        if (empty($rule_sid)) {
            return returnCode(10007, '获取计费信息失败');
        }

        $get_date = $this->get_date($one_contract['end_time'], $this->request['month_total']);
        $rule_data = json_encode([
            "_id" => $rule_sid,
            "facts" => ["Quantity" => $get_date['month_total']]
        ], JSON_UNESCAPED_UNICODE);
        $result = $this->rule->post('/decision/exec', $rule_data);
        $rule_details = $result['content'] ?? [];
        info(__METHOD__, ['tip' => '获取计费引擎信息', 'data' => $rule_data, 'result' => $result]);
        if ($result['code'] != 0 || !isset($rule_details['TotalFee'])) {
            return returnCode(10007, '获取计费信息异常');
        }

        // 查询项目信息
        $pm = $this->pm->post('/project/show', ['project_id' => $one_contract['project_id']]);
        // space
        $space_branche = $this->pm->post('/space/branch', ['space_id' => $one_contract['space_id']]);
        $branch = $space_branche['content'] ?? [];
        $branch_info = SpaceModel::parseBranch($branch, '-');

        $one_contract['project_name'] = $pm['content']['project_name'] ?? '';
        $one_contract['space_name_full'] = $branch_info['space_name_full'] ?? '';
        $one_contract['plate'] = $this->request['plate'];
        $one_contract['cost_begin_time'] = $get_date['begin'];
        $one_contract['cost_end_time'] = $get_date['end'];
        $one_contract['plate'] = $this->request['plate'];
        $one_contract['begin_time'] = date("Y-m-d", $one_contract['begin_time']);
        $one_contract['end_time'] = date("Y-m-d", $one_contract['end_time']);
        $one_contract['platform_type_tag_id'] = 1525;
        $one_contract['rule_type_tag_id'] = 1509;

        return returnCode(0, '计费成功', [
            'pay_amount' => round($rule_details['TotalFee'], 2),
            'coupon_amount' => 0,
            'charge_info' => $one_contract
        ]);
    }

    /**
     * @param $end
     * @param $month_total
     * @return array
     * 计算缴费有效期
     */
    protected function get_date($end, $month_total)
    {
        $begin = date("Y-m-d", strtotime(" +1 day", strtotime(date("Y-m-d", $end))));

        $tmp = strtotime("first day of +" . $month_total . " month " . $begin);
        $end = date("Y-m-d", strtotime(date('Y-m', $tmp) . "-01") - 1);

        $j = date("j", strtotime($begin));
        $t = date("t", strtotime($begin));
        if ($j != 1) {
            $one = round(($t - $j + 1) / $t, 6);
            $month_total = bcadd($month_total - 1, $one, 6);
        }
        return ['begin' => $begin, 'end' => $end, 'month_total' => $month_total];
    }
}