<?php

namespace Rule;

class RuleModel
{
    private $pm;

    private $rule;

    private $ruleConfig;

    private $params;

    public function __construct()
    {
        $this->pm = new \Comm_Curl(['service' => 'pm', 'format' => 'json']);
        $this->rule = new \Comm_Curl(['service' => 'rule', 'format' => 'json']);
        $this->ruleConfig = $this->ruleConfig();
        if (!$this->ruleConfig) {
            return ['code' => 10001, 'msg' => '规则配置文件读取失败'];
        }
    }


    public function setParams($params)
    {
        $this->params = $params;
        return $this;
    }


    public function getParams()
    {
        return $this->params;
    }


    public function cost()
    {
        $params = $this->getParams();
        if (isTrueKey($params, 'rule_id', 'business_params', 'type', 'billing_account_name', 'project_id') == false) {
            return ['code' => 10001, 'msg' => '计费参数缺失'];
        }
        $billing_account_name = trim($params['billing_account_name']);
        if (!isset($this->ruleConfig[$billing_account_name])) {
            return ['code' => 10002, 'msg' => '该科目规则文件未配置'];
        }

        $rule_info = array_column($this->ruleConfig[$billing_account_name], null, 'type');
        $type = trim($params['type']);
        if (!isset($rule_info[$type])) {
            return ['code' => 10002, 'msg' => '该类型规则文件未配置'];
        }

        $rule_info = $rule_info[$type];
        $method = $rule_info['method'];
        if (!method_exists($this, $method)) {
            return ['code' => 10002, 'msg' => '业务接口不存在'];
        }

        $rsp = $this->$method($params['business_params'], $rule_info['params']);
        if (0 != $rsp['code']) {
            return ['code' => 10002, 'msg' => $rsp['msg']];
        }
        $result = $this->rule->header(["Content-Type:application/json", "Project-Id:{$params['project_id']}"])
            ->post('/decision/exec', json_encode([
                '_id' => $params['rule_id'],
                'facts' => $rsp['content']
            ]));
        log_message(__METHOD__ . '----计费接口响应------' . json_encode([$result, $rsp['content']]));
        if (!$result || 0 != $result['code']) {
            return ['code' => 10002, 'msg' => $result['message'] ?? '服务异常'];
        }
        $result = $this->checkResponseData($rule_info['response'], $result['content']);
        if (false === $result) {
            return ['code' => 10002, 'msg' => '响应数据不规范'];
        }
        return ['code' => 0, 'content' => array_merge(['unit' => $rule_info['unit']], $result), 'msg' => 'success'];
    }


    private function getHouseArea($params = [], $expect_params = [])
    {
        if (!isset($params['billing_calculate_num'])) {
            return ['code' => 10001, 'msg' => 'billing_calculate_num参数缺失'];
        }

        $content = [];
        foreach ($expect_params as $k => $v) {
            $content[$k] = $params[$v];
        }
        return ['code' => 0, 'content' => $content, 'msg' => 'success'];
    }


    private function getWaterQuantity($params = [], $expect_params = [])
    {
        if (!isset($params['billing_calculate_num'])) {
            return ['code' => 10001, 'msg' => 'billing_calculate_num参数缺失'];
        }
        $content = [];
        foreach ($expect_params as $k => $v) {
            $content[$k] = $params[$v];
        }
        return ['code' => 0, 'content' => $content, 'msg' => 'success'];
    }


    private function getElectricQuantity($params = [], $expect_params = [])
    {
        if (!isset($params['billing_calculate_num'])) {
            return ['code' => 10001, 'msg' => 'billing_calculate_num参数缺失'];
        }
        $content = [];
        foreach ($expect_params as $k => $v) {
            $content[$k] = $params[$v];
        }
        return ['code' => 0, 'content' => $content, 'msg' => 'success'];
    }


    private function getPenaltyParams($params = [], $expect_params = [])
    {
        if (!isset($params['total_days']) || !isset($params['amount'])) {
            return ['code' => 10001, 'msg' => 'total_days或amount参数缺失'];
        }
        $content = [];
        foreach ($expect_params as $k => $v) {
            $content[$k] = $params[$v];
        }
        return ['code' => 0, 'content' => $content, 'msg' => 'success'];
    }

    private function ruleConfig()
    {
        $json = file_get_contents(__DIR__ . '/Rule.json');
        return json_decode($json, true);
    }


    private function checkResponseData($expect_data, $content)
    {
        $data = [];
        foreach ($expect_data as $k => $v) {
            if (!isset($content[$k])) {
                return false;
            }
            $data[$v] = $content[$k];
        }
        return $data;
    }

}