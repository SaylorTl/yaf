<?php
/**
 * Created by PhpStorm.
 * User: wuli
 * Date: 2020/12/30
 * Time: 10:07
 */

namespace Charging\App\Zhsq;

use Charging\App\CommonModel;
use Charging\ConstantModel;

class TempModel extends BasicModel implements CommonModel
{
    /**
     * @return mixed|void
     * rule_type_tag_id(业务类型标签id)  1509：月卡  1510：临停
     * inout_type_tag_id(进出场类型标签id):  1351：进场 1353：出场
     */
    public function cost()
    {
        if (!isTrueKey($this->request, 'plate')) {
            return returnCode(10001, '缺少车牌信息');
        }

        $plate = $this->request['plate'];
        $car_info = $this->car->post('/id', ['plate' => $plate]);
        $car_id = $car_info['content'] ?? 0;
        if (empty($car_id)) {
            return returnCode(10002, '获取不到车辆信息');
        }

        // 查询进场信息
        $show = [
            'car_id' => $car_id,
            'inout_type_tag_id' => 1351,
            'time_out_end' => 0
        ];
        if (isTrueKey($this->request, 'project_id')) {
            $show['project_id'] = $this->request['project_id'];
        }
        $in_info = $this->device->post('/inout/show', $show);
        $in = $in_info['content'] ?? [];
        if (empty($in)) {
            $show['tabletime'] = date('Ym', strtotime(date('Y-m-01') . " - 1 month"));
            $in_info = $this->device->post('/inout/show', $show);
            $in = $in_info['content'] ?? [];
        }
        if (empty($in)) {
            return returnCode(10002, '该车没有进场记录信息');
        }
        $time_in = $in['confirm_time'] ?: $in['time_in'];
        if ($time_in <= 0 || empty($in['device_id'])) {
            return returnCode(10002, '获取进场信息失败，无法计费');
        }

        // 查询项目配置信息
        $station_cfg = $this->pm->post('/project/stationcfg/lists', [
            'project_ids' => [$in['project_id']],
            'manage_type' => 1523, // 运营中
            'platform_type' => 1525 //智慧社区
        ]);
        $station_cfg = $station_cfg['content'] ?? [];
        if (empty($station_cfg)) {
            info(__METHOD__, ['tip' => '项目配置:' . $plate, 'project' => $in['project_id'], 'result' => $station_cfg]);
            return returnCode(10002, '该车牌所属项目未上线');
        }

        // 查询车辆对应的计费规则，先查车辆关联车位空间的计费规则，没有在去查询项目设备空间对应的计费规则
        $rule_sid = 0;

        // 查询临停车信息
        $temp_info = $this->contract->post('/contractdetail/lists', [
            'car_id' => $car_id,
            'contract_status_tag_id' => 1555,
            'rule_type_tag_id' => 1510,
            'project_id' => $in['project_id']
        ]);
        $temp = $temp_info['content'] ?? [];
        if (!empty($temp) && count($temp) > 1) {
            return returnCode(10002, '该车配置的临停信息有误');
        }
        if (!empty($temp) && count($temp) == 1) {
            // 车位
            $place_info = $this->contract->post('/contractplace/lists', [
                'contract_ids' => [$temp[0]['contract_id']],
                'is_del' => 'N'
            ]);
            $places = $place_info['content'] ?? [];
            $place_ids = $places ? array_unique(array_column(($places ?: []), 'place_id')) : [];
        }
        if (isset($place_ids) && !empty($place_ids)) {
            $parking_info = $this->pm->post('/parkplace/lists', ['place_ids' => $place_ids]);
            info(__METHOD__, ['tip' => '车位信息:' . $plate, 'place' => $place_ids, 'result' => $parking_info]);
            $parking = $parking_info['content'] ?? [];
            $place_space_ids = array_unique(array_column(($parking ?: []), 'space_id'));
        }
        // 计费空间配置
        if (isset($place_space_ids) && !empty($place_space_ids)) {
            $config_info = $this->cost->post('/businessConfigSpace/lists', [
                'space_ids' => $place_space_ids,
                'deleted' => 'N',
                'page' => 1,
                'pagesize' => 2
            ]);
            info(__METHOD__, ['tip' => '计费空间配置:' . $plate, 'space' => $place_space_ids, 'result' => $config_info]);
            $config_space = $config_info['content'] ?? [];
            $business_config_ids = array_unique(array_column(($config_space ?: []), 'business_config_id'));
        }
        // 计费配置
        $rule_type_tag_id = $temp[0]['rule_type_tag_id'] ?? 1510;
        $tag = $this->tag->post('/tag/show', ['tag_id' => $rule_type_tag_id]);
        $cost_account_id = $tag['content']['tag_val'] ?? 1600;
        if (isset($business_config_ids) && $business_config_ids) {
            $rule_cost = $this->cost->post('/businessConfig/lists', [
                'business_config_ids' => $business_config_ids,
                'page' => 1,
                'pagesize' => 1,
                'deleted' => 'N',
                'project_id' => $in['project_id'],
                'billing_account_tag_id' => $cost_account_id
            ]);
            info(__METHOD__, [
                'tip' => '计费配置:' . $plate,
                'business_ids' => $business_config_ids,
                'result' => $rule_cost
            ]);
            $rule = $rule_cost['content'] ?? [];
            $rule_sid = $rule[0]['rule_id'] ?? 0;
            if ($rule && count($rule) > 1) {
                return returnCode(10002, '该车配置的临停规则信息有误');
            }
        }

        // 查询设备空间对应的计费规则 status_tag_id: 1381(启用) 根据科目标签 + 空间id + 状态标签   查规则
        if ($rule_sid == 0) {
            $rule_data = [
                'space_id' => $place_space_ids[0] ?? $in['device_space_id'],
                'billing_account_tag_id' => $cost_account_id,
                'status_tag_id' => 1381,
                'project_id' => $in['project_id']
            ];
            $rule_cost = $this->cost->post('/businessConfig/getRule', $rule_data);
            info(__METHOD__, ['tip' => '计费规则-' . $plate, 'data' => $rule_data, 'result' => $rule_cost]);
            $rule_sid = $rule_cost['content']['rule_id'] ?? 0;
        }

        // 检测计费规则信息
        if ($rule_sid == 0) {
            return returnCode(10007, '获取计费信息失败');
        }

        // 获取计费引擎计费信息
        $now = time();
        $rule_data = json_encode([
            "_id" => $rule_sid,
            "facts" => ["ParkingTime" => bcdiv($now - $time_in, 3600, 6)]
        ], JSON_UNESCAPED_UNICODE);
        $result = $this->rule->post('/decision/exec', $rule_data);
        $rule_details = $result['content'] ?? [];
        info(__METHOD__, ['tip' => '获取计费引擎信息：' . $plate, 'data' => $rule_data, 'result' => $result]);
        if ($result['code'] != 0 || !isset($rule_details['TotalFee'])) {
            return returnCode(10007, '计费错误');
        }

        // 查询是否存在历史账单
        $tabletime = date("Ym", rSnowFlake($in['through_id']));
        $sum_result = $this->device->post('/inout/tnum/sum', [
            'through_id' => $in['through_id'],
            'tabletime' => $tabletime
        ]);
        info(__METHOD__, ['tip' => '查询进出场关联订单号' . $plate, 'result' => $sum_result]);

        $sum = $sum_result['content'] ?? 0;
        $pay_amount = (int)bcmul(round($rule_details['TotalFee'], 2), 100);
        $pay_amount = $pay_amount <= $sum ? 0 : $pay_amount - $sum;

        // 查询停车场区域信息
        $branch = $this->pm->post('/space/branch', ['space_id' => $in['device_space_id']]);
        $station_space = [];
        if ($branch['content']) {
            array_map(function ($m) use (&$station_space) {
                if ($m['space_type'] == 452) {
                    $station_space = $m;
                }
            }, $branch['content']);
        }

        $pm = $this->pm->post('/project/show', ['project_id' => $in['project_id']]);
        $return = [
            'car_id' => $car_id,
            'plate' => $plate,
            'project_id' => $in['project_id'],
            'project_name' => $pm['content']['project_name'] ?? '',
            'address_detail' => $pm['content']['address_detail'] ?? '',
            'parking_time' => $now - $time_in,
            'time_begin' => date("Y-m-d H:i:s", $time_in),
            'time_end' => date("Y-m-d H:i:s", $now),
            'through_id' => $in['through_id'],
            'platform_type_tag_id' => 1525,
            'rule_type_tag_id' => $rule_type_tag_id,
            'inout_id' => $in['inout_id'] ?? 0,
            'device_id' => $in['device_id'],
            'device_space_id' => $in['device_space_id'],
            'detail_uuid' => strtoupper(uuid('zerocharge', '')),
            'device_station_space' => $station_space['space_name'] ?? '',
            'pay_amount' => $pay_amount / 100
        ];

        // 0元记录缓存信息进行开闸
        if ($pay_amount == 0) {
            $save = $this->redis->setex(
                ConstantModel::ZERO_REDIS . '_' . $return['detail_uuid'],
                ConstantModel::CHARGE_TIMES,
                json_encode($return, JSON_UNESCAPED_UNICODE)
            );
            info(__METHOD__, ['tip' => '0元计费缓存结果' . $plate, 'result' => $save]);
        }

        return returnCode(0, '计费成功', [
            'pay_amount' => $pay_amount / 100,
            'coupon_amount' => 0,
            'charge_info' => $return
        ]);
    }
}