<?php

class Comm_Redis {
    private static $instance;

    public static function getInstance($db_name = 'default') {
        $config = getConfig('redis.ini')->redis->$db_name;
        return self::initDb($db_name, $config);
    }

    private static function initDb($db_name, $config) {
        if (isset(self::$instance[$db_name])) {
            return self::$instance[$db_name];
        }

        $redis = new Redis();
        $redis->connect($config->host, $config->port);
        if($config->auth){
            $redis->auth($config->auth);
        }
        self::$instance[$db_name] = $redis;
        return self::$instance[$db_name];
    }

}

?>
