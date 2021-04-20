<?php
/**
 * Created by PhpStorm.
 * User: wuli
 * Date: 2020/12/30
 * Time: 10:07
 */

namespace Charging\App\Zhsq;

use Charging\ConstantModel;
use Charging\ConstantModel as Cons;
use Comm_Curl;
use Comm_Redis;
use OrderModel;
use ReflectionException;

class BasicModel
{
    protected $pm;

    protected $car;

    protected $cost;

    protected $rule;

    protected $contract;

    protected $redis;

    protected $device;

    protected $tag;

    /**
     * @var
     * 请求参数
     */
    protected $request;

    public function __construct(Array $request)
    {
        $this->request = $request;

        $header = ["Content-Type:application/json"];
        $this->pm = new Comm_Curl(['service' => 'pm', 'format' => 'json']);
        $this->car = new Comm_Curl(['service' => 'car', 'format' => 'json']);
        $this->cost = new Comm_Curl(['service' => 'billing', 'format' => 'json']);
        $this->contract = new Comm_Curl(['service' => 'contract', 'format' => 'json']);
        $this->tag = new Comm_Curl(['service' => 'tag', 'format' => 'json']);
        $this->device = new Comm_Curl(['service' => 'device', 'format' => 'json']);
        $this->rule = new Comm_Curl(['service' => 'rule', 'format' => 'json', 'header' => $header]);
        $this->redis = Comm_Redis::getInstance();
        $this->redis->select(8);
    }

    /**
     * 计费列表
     * cost_type(计费类型)  1、停车费-月卡  2、停车费-临停
     */
    public function getChargeLists()
    {
        if (!isTrueKey($this->request, 'cost_type')) {
            rsp_die_json(10001, '请求信息不全');
        }
        if (!is_array($this->request['cost_type'])) {
            $this->request['cost_type'] = json_decode($this->request['cost_type'], true);
        }

        $platform = $this->request['platform'] ?? 'Zhsq';
        if (!isset(Cons::CHARGE_METHODS[$platform])) {
            rsp_die_json(10001, '计费失败');
        }

        // 检测UUID信息
        $uuid_info = [];
        if (isTrueKey($this->request, 'charge_uuid')) {
            $charge_uuid = $this->redis->get(OrderModel::PAY_AMOUNT_REDIS_KEY . $this->request['charge_uuid']);
            $uuid_info = !empty($charge_uuid) ? json_decode($charge_uuid, true) : [];
        } else {
            $this->request['charge_uuid'] = strtoupper(uuid('charging', ''));
        }

        $fees = [];
        $total_pay_amount = $set_pay_amount = 0;
        foreach ($this->request['cost_type'] as $v) {
            if (!isset(Cons::CHARGE_METHODS[$platform][$v])) {
                continue;
            }
            $info = Cons::CHARGE_METHODS[$platform][$v];
            $class = $info['class'];
            $charge_info = (new $class($this->request))->cost();
            $content = $charge_info['content'];
            $set_charge_info = ['pay_amount' => 0, 'coupon_amount' => 0, 'charge_info' => []];
            if ($charge_info['code'] == 0) {
                $charge_uuid['detail'][$v] = $content;
                $pay_amount = (int)bcmul($content['pay_amount'], 100);
                $coupon_amount = (int)bcmul($content['coupon_amount'], 100);
                $real_amount = $pay_amount - $coupon_amount;
                $uuid_info['detail'][$v]['amount'] = $real_amount <= 0 ? 0 : $real_amount;
                $total_pay_amount += $pay_amount;
            } else {
                info(__METHOD__, [
                    'tip' => "{$info['name']}失败：" . $charge_info['message'],
                    'data' => $this->request
                ]);
                $set_charge_info['error'] = $charge_info['message'];
            }

            $arr = ['charge_name' => $info['name'], 'order_type' => $info['order_type'], 'cost_type' => $v];
            $charge_detail = $content ?: $set_charge_info;
            $arr = array_merge($arr, $charge_detail);
            $fees[] = $arr;
        }
        // 记录应付总金额
        if (!empty($uuid_info['detail'])) {
            array_map(function ($m) use (&$set_pay_amount) {
                $set_pay_amount += $m['amount'];
            }, $uuid_info['detail']);
        }
        $uuid_info['total_pay_amount'] = $set_pay_amount;
        $charge_uuid = json_encode($uuid_info, JSON_UNESCAPED_UNICODE);
        $this->redis->setex(
            OrderModel::PAY_AMOUNT_REDIS_KEY . $this->request['charge_uuid'],
            ConstantModel::CHARGE_TIMES,
            $charge_uuid
        );

        // 记录应付总金额
        $total_pay_amount = round($total_pay_amount / 100, 2);
        rsp_success_json([
            'charge_uuid' => $this->request['charge_uuid'],
            'total_pay_amount' => $total_pay_amount,
            'charge_detail' => $fees
        ], '请求成功');
    }

    /**
     * @param $params
     * @return array
     * 检测计费金额
     */
    public function checkPayAmount($params)
    {
        if (!isTrueKey($params, 'amount', 'charge_uuid')) {
            return returnCode(10001, '请求信息错误');
        }

        // 校验应付总金额
        $redis = Comm_Redis::getInstance();
        $redis->select(8);
        $chargeUUid = $redis->get(OrderModel::PAY_AMOUNT_REDIS_KEY . $params['charge_uuid']);
        $chargeUUid = !empty($chargeUUid) ? json_decode($chargeUUid, true) : [];
        $orderTypes = array_unique(array_column($params['sub_orders'], 'trade_source_tag_id'));
        $total_pay_amount = 0;
        if (!empty($orderTypes)) {
            array_map(function ($m) use (&$total_pay_amount, $chargeUUid) {
                $total_pay_amount += $chargeUUid['detail'][$m]['amount'] ?? 0;
            }, $orderTypes);
        }
        $arr = array_intersect($orderTypes, ConstantModel::CHARGE_ORDER_TYPES);
        if ((empty($chargeUUid) || $total_pay_amount > (int)$params['amount']) && !empty($arr)) {
            return returnCode(10001, '检测计费金额错误');
        }
        return returnCode(0, '检测成功');
    }
}