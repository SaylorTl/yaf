<?php

namespace Receipt;

use Exception;

class ReceiptModel
{
    //解密秘钥
    protected $key;
    //偏移量
    protected $iv;
    //appid
    protected $appid;
    //secret
    protected $secret;
    //打印收据第三方地址
    protected $url;
    //当前时间
    protected $time;
    //签章信息
    protected $signature;
    //产权人信息
    protected $owner;
    //sign_data 返回md5值
    protected $receipt_md5;

    public function __construct($tnum, $data, $paidtime, $signature, $owner)
    {
        try {
            $config = getConfig('other.ini');
            $this->key = $config->get('receipt.key') ?? '';
            $this->iv = $config->get('receipt.iv') ?? '';
            $this->url = $config->get('receipt.url') ?? '';
            $this->appid = $config->get('receipt.appid') ?? '';
            $this->secret = $config->get('receipt.secret') ?? '';
            $this->time = time();
            $this->signature = $signature;
            $this->owner = $owner;
            if (!$this->key || !$this->iv || !$this->url || !$this->appid || !$this->secret) {
                throw new \Exception('收据打印配置参数缺失');
            }
            $this->get_pdf($tnum, $data, $paidtime);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function get_pdf($tnum, $data, $paidtime)
    {
        try {
            $rsp = $this->_sign_data($tnum, $data, $paidtime);
            $this->receipt_md5 = $rsp['md5'];
            $pdata = [
                "timestamp" => $this->time,
                "file" => $rsp['file'],
            ];
            $str = json_encode($pdata, JSON_UNESCAPED_UNICODE);
            $data1 = $this->encode_crypt($this->key, $this->iv, $str);
            $string4sign =
                $this->appid . $this->time . $data1 . $this->secret;
            $hash = md5(md5($string4sign));
            $url = $this->url . "/api/file?appid=" . $this->appid . "&timestamp=" . $this->time . "&signature=" . $hash;
            $fp = fopen($data['file_path'], 'w+');
            $output = curl_text('POST', $url, ['filltext' => $data1]);
            if ($output === false) throw  new Exception('文件下载失败');
            fwrite($fp, $output);
            fclose($fp);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function _sign_data($tnum, $data, $paidtime)
    {
        try {
            $operateTime = date('Y/m/d H:i:s', $paidtime);
            $amount = $data['data']['csmActualCollectMoney'];
            $paymethod = $data['data']['csmChargeMethod'];
            //房屋名称
            $htHouseName = $data['data']['itemRecord'][0]['detailRecord'][0]['htHouseName'];
            //用户名称
            $yhy_customer = $data['data']['htCustomerName'] ?? '';
            $customer = isset($this->owner['proprietor_name']) && !empty($this->owner['proprietor_name']) ? $this->owner['proprietor_name'] : '';
            $customer = empty($customer) ? $yhy_customer : $customer;
            $remark = '备注: ';
            //计费周期
            $billing_cycle = $data['data']['startDateString'] . '-' . $data['data']['endDateString'];
            $remark .= '计费周期：' . $billing_cycle . '; ';
            //抄表日期
            if (!empty($data['data']['meterDate'])) {
                $remark .= '抄表日期：' . $data['data']['meterDate'] . '; ';
            }
            $body = [];
            $c6_total = $c7_total = $c8_total = $c9_total = $c10_total = $c11_total = $c12_total = 0;
            foreach ($data['data']['itemRecord'] as $k => $v) {
                $index = 'r' . ($k + 1);
                $body[$index] = [
                    'c1' => $v['itemName'],
                    'c2' => !empty($v['hmPreviousReading']) ? $v['hmPreviousReading'] : '',
                    'c3' => !empty($v['hmCurrentReading']) ? $v['hmCurrentReading'] : '',
                    'c4' => isset($v['pciChargeNumber']) ? $v['pciChargeNumber'] : '',
                    'c5' => $v['itemName'] != '居民用水' ? $v['pciChargefeesStandards'] : '',
                    'c6' => retain_two_decimal($v, 'thisMonthMoney'),
                    'c7' => retain_two_decimal($v, 'pastMonthMoney'),
                    'c8' => retain_two_decimal($v, 'pastYearMoney'),
                    'c9' => retain_two_decimal($v, 'penaltyMoney'),
                    'c10' => retain_two_decimal($v, 'payTip'),
                    'c11' => retain_two_decimal($v, 'penaltyAdjustMoney'),
                    'c12' => retain_two_decimal($v, 'kitsugiCharge'),
                ];
                $c6_total = bcadd($c6_total, $v['thisMonthMoney'] ?? 0, 2);
                $c7_total = bcadd($c7_total, $v['pastMonthMoney'] ?? 0, 2);
                $c8_total = bcadd($c8_total, $v['pastYearMoney'] ?? 0, 2);
                $c9_total = bcadd($c9_total, $v['penaltyMoney'] ?? 0, 2);
                $c10_total = bcadd($c10_total, $v['payTip'] ?? 0, 2);
                $c11_total = bcadd($c11_total, $v['penaltyAdjustMoney'] ?? 0, 2);
                $c12_total = bcadd($c12_total, $v['kitsugiCharge'] ?? 0, 2);
            }
            //行
            $row = [
                'c1' => '',
                'c2' => '',
                'c3' => '',
                'c4' => '',
                'c5' => '',
                'c6' => '',
                'c7' => '',
                'c8' => '',
                'c9' => '',
                'c10' => '',
                'c11' => '',
                'c12' => '',
            ];
            //需要补足的行数
            $row_index = count($body);
            $pad_space_row = 9 - count($body) < 0 ? 0 : 9 - count($body);
            for ($i = 1; $i < $pad_space_row; $i++) {
                $row_index += 1; //已存在总行数
                $index = 'r' . $row_index;
                if ($i == 1) {
                    //预存金额
                    $tmp_row = $row;
                    $tmp_row['c1'] = '本次存零';
                    $tmp_row['c12'] = retain_two_decimal($data['data'], 'csmDepositMoney');
                    $body[$index] = $tmp_row;
                } else {
                    $body[$index] = $row;
                }
            }
            //是否减免违约金
            if (!$this->signature['collect_penalty']) {
                //优惠金额不为0,才需要显示
                if (!empty((float)$c9_total)) {
                    $remark .= '优惠信息：违约金全免活动,减免金额（' . $c9_total . '元）';
                }
            }
            //合计
            $row['c1'] = '合计';
            $row['c6'] = $c6_total;
            $row['c7'] = $c7_total;
            $row['c8'] = $c8_total;
            $row['c9'] = $c9_total;
            $row['c10'] = $c10_total;
            $row['c11'] = $c11_total;
            $row['c12'] = $c12_total;
            $last_row_index = 'r' . ($row_index + 1);
            $body[$last_row_index] = $row;
            $pdata = [
                "tnum" => $tnum,
                "timestamp" => $this->time,
                "seal" => $this->signature['seal'],//签章ID
                "template" => $this->signature['template'],
                "location" => $this->signature['project_name'],
                "reason" => "物业费",
                "subject" => $this->signature['project_name'],
                "contract" => "400-100-100",
                "header" => [
                    "main" => $this->signature['company_name'],
                    "sub" => $this->signature['project_name'],
                ],
                "table" => [
                    "header" => [
                        "left" => "客户名称:" . $customer . '/' . $htHouseName,
                        "center" => "收费日期:" . $operateTime,
                        "right" => "订单号:" . $tnum,
                    ],
                    "body" => $body,
                    "summary" => [
                        "left" => "金额（大写）:" . NumToCNMoney($amount),
                        "center" => "",//模板未激活
                        "right" => "￥：" . sprintf("%.2f", $amount),
                    ],
                    "memo" => [
                        "left" => $remark,
                        "center" => "",//模板未激活
                        "right" => "",//模板未激活
                    ],
                    "footer" => [
                        "left" => "收款方式:" . $paymethod,
                        "center" => "收款人:" . $this->signature['company_name'],
                        "right" => "",
                    ],
                ],
            ];
            $str = json_encode($pdata, JSON_UNESCAPED_UNICODE);
            $data1 = $this->encode_crypt($this->key, $this->iv, $str);
            $string4sign =
                $this->appid . $this->time . $data1 . $this->secret;
            $hash = md5(md5($string4sign));
            $url = $this->url . "/api/sign?appid=" . $this->appid . "&timestamp=" . $this->time . "&signature=" . $hash;
            $response = curl_text('POST', $url, array("filltext" => $data1));
            log_message(__METHOD__ . '------收据签名响应：' . json_encode([$response]));
            $rsp = json_decode($response, true);
            if (!$rsp || $rsp['code'] != 0) throw new Exception('数据加密失败');
            return $rsp;
        } catch (\Exception $e) {
            throw $e;
        }
    }


    /**
     * encode_crypt 加密固定key与iv偏移量
     * @param $encrypt
     * @return int|string
     * @author storm_fu
     * @date   2019/09/05
     */
    private function encode_crypt($key, $iv, $encrypt)
    {
        // 加密
        $encode = base64_encode(
            openssl_encrypt($encrypt, "AES-128-CBC", $key, true, $iv)
        );
        if ($encode) {
            return $encode;
        } else {
            return false;
        }
    }

    /**
     * decode_crypt解密固定key与iv偏移量
     * @param $encrypt
     * @return int|string
     * @author storm_fu
     * @date   2019/09/05
     */
    private function decode_crypt($key, $iv, $encrypt)
    {
        $encrypt = base64_decode($encrypt);
        $decrypt = openssl_decrypt($encrypt, 'AES-128-CBC', $key, true, $iv);
        if ($decrypt) {
            return $decrypt;
        } else {
            return false;
        }
    }

    /**
     * 读取该类属性
     * @return mixed
     */
    public function get_params()
    {
        return $this->receipt_md5;
    }
}