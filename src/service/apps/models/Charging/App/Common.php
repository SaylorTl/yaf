<?php


namespace Charging\App;

interface CommonModel
{
    /**
     * @return mixed
     * 计费信息
     */
    public function cost();
}