<?php

namespace Rule;

class PenaltyModel
{

    /**
     * billing_penalty_day 违约次数
     * @param $account
     * @param $data
     * @return bool|false|float|int
     */
    public static function getPenaltyTimeFrame($account, $data)
    {
        $penalty_day = $penalty_end_time = 0;
        //违约金计算开始时间
        $penalty_begin_time = date('Y-m-d 00:00:00', strtotime('+1day', strtotime($data['billing_end_time'])));
        if (1409 === $account['if_cycle_tag_id']) {
            //周期计费
            $billing_cycle_type_tag_id = $account['billing_schedule_data']['billing_cycle_type_tag_id'];
            switch ($billing_cycle_type_tag_id) {
                case 1422://天
                    $penalty_begin_time = date('Y-m-d 00:00:00', strtotime('+1day', strtotime($penalty_begin_time)));
                    break;
                case 1456://周
                    $penalty_begin_time = date('Y-m-d 00:00:00', strtotime('+1week', strtotime($penalty_begin_time)));
                    break;
                case 1464://月
                    $penalty_begin_time = date('Y-m-d 00:00:00', strtotime('+1 month', strtotime($penalty_begin_time)));
                    break;
                case 1477://季度
                    $penalty_begin_time = date('Y-m-d 00:00:00', strtotime('+3month ', strtotime($penalty_begin_time)));
                    break;
                case 1494://年
                    $penalty_begin_time = date('Y-m-d 00:00:00', strtotime('+1year', strtotime($penalty_begin_time)));
                    break;
                default:
                    return false;
            }
        }

        if (1527 === $account['billing_penalty_cycle_tag_id']) {//天

            $penalty_day = floor($data['billing_penalty_day'] * $account['billing_penalty_cycle']);
            $penalty_end_time = date('Y-m-d 23:59:59', strtotime("$penalty_begin_time +$penalty_day day"));
        } else if (1529 === $account['billing_penalty_cycle_tag_id']) {//周

            $penalty_day = floor($data['billing_penalty_day'] * (7 * $account['billing_penalty_cycle']));
            $penalty_end_time = date('Y-m-d 23:59:59', strtotime("$penalty_begin_time +$penalty_day day"));

        } else if (1528 === $account['billing_penalty_cycle_tag_id']) {//月

            $first_month_day = date('Y-m-01', strtotime($penalty_begin_time));
            $last_day = date('d', strtotime("$first_month_day +1 month -1 day"));
            $penalty_day = floor($data['billing_penalty_day'] * ($last_day * $account['billing_penalty_cycle']));
            $penalty_end_time = date('Y-m-d 23:59:59', strtotime("$penalty_begin_time +$penalty_day day"));
        }

        return [
            'penalty_start_time' => $penalty_begin_time,
            'penalty_end_time' => $penalty_end_time
        ];
    }
}