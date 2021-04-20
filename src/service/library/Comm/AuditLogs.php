<?php

/**
 * 审计日志推送
 * Class Comm_AuditLogs
 */
class Comm_AuditLogs
{
    /**
     * 审计日志监控器队列名称
     * @var string
     */
    private static $log_monitor_list = 'log:monitor';
    
    /**
     * 推送数据到审计日志监控脚本
     * @param  string  $kind  类型
     * @param  string  $sid  第三方唯一ID（资源ID）
     * @param  string  $business  业务名称
     * @param  string  $mode  模式，如：添加、更新、删除......等等
     * @param  array  $data  数据
     * @param  string  $result  操作结果，如：成功、失败
     * @return mixed
     */
    public static function push($kind, $sid, $business, $mode, $data, $result)
    {
        try {
            $config = getConfig('redis.ini');
            $redis = Comm_Redis::getInstance();
            $redis->select($config->get('redis.default.database') ?: 8);
            $params = [
                'app_id' => $_SESSION['oauth_app_id'] ?? 0,
                'sub_app_id' => $_SESSION['oauth_subapp_id'] ?? 0,
                'kind' => $kind,
                'sid' => $sid,
                'operation_time' => time(),
                'username' => $_SESSION['employee_id'] ?? '',
                'role' => $_SESSION['member_role_name'] ?? '未知',
                'ip' => getip(),
                'business' => $business,
                'mode' => $mode,
                'result' => $result,
                'path' => $_GET['permissions_key'] ?? '',
                'data' => json_encode($data)
            ];
            Comm_EventTrigger::push('audit_log_monitor', $params);
//            $params = json_encode($params, JSON_UNESCAPED_UNICODE);
//            $res = $redis->lpush(self::$log_monitor_list, $params);
//            if (!$res) {
//                throw new Exception('审计日志数据发送失败，队列：'.self::$log_monitor_list.',数据：'.$params);
//            }
//            return $res;
        } catch (\Exception $e) {
            $info = [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'error' => $e->getMessage(),
            ];
            log_message('----Comm_AuditLogs/'.__FUNCTION__.'----'.json_encode($info, JSON_UNESCAPED_UNICODE));
            return false;
        }
    }
}