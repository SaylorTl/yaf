<?php


class Base
{
    protected function hset($key, $secondKey, $data)
    {
        return \Comm_Redis::getInstance()->hset($key, $secondKey, $data);
    }

    protected function hget($key, $secondKey, $data)
    {
        return \Comm_Redis::getInstance()->hget($key, $secondKey);
    }

    protected function hgetAll($key, $secondKey, $data)
    {
        return \Comm_Redis::getInstance()->hgetall($key);
    }

    protected function hdel($key, $secondKey, $data)
    {
        return \Comm_Redis::getInstance()->hdel($key, $secondKey);
    }
}