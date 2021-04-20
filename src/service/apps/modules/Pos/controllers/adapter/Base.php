<?php

class Base {

	protected $adapter;
	protected $pm;
	protected $etam_url; //收银台微服务地址
    protected $station_adapter; // 车场适配器微服务地址
    protected $jz;  //极致适配器
    protected $cost;
	public function __construct(){
		$this->adapter = new Comm_Curl(['service' => 'adapter', 'format' => 'json']);
		$this->pm = new Comm_Curl([ 'service'=>'pm','format'=>'json']);
		$config = getConfig('other.ini');
        $this->etam_url = $config->get('receipt.order.url');
        $this->station_adapter = new Comm_Curl(['service' => 'station_adapter', 'format' => 'json']);

        $this->jz = new Comm_Curl(['service' => 'jz', 'format' => 'json']);

        $this->cost = new Comm_Curl(['service' => 'billing', 'format' => 'json']);
	}
}