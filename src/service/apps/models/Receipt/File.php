<?php
namespace Receipt;
use Exception;

class FileModel {

    //文件微服务
    protected  $file;
    //资源id映射
    protected  $resource_map = [
      'receipt' => 10029,
    ];
    public function  __construct()
    {
        $this->file = new \Comm_Curl(['service' => 'fileupload', 'format' => 'json']);
    }

    public function upload($type,$file){
        try{
            $receipt_id = resource_id_generator($this->resource_map[$type]);
            if(!$receipt_id) throw new Exception('资源id生成失败');
            $data = [
               'file'=>new \CURLFILE($file),
                'resource_type'=> $type,
                'resource_id'=> $receipt_id,
            ];
            $config = getConfig('ms.ini');
            $url = $config->get('fileupload.url');
            $rsp = $this->_curl_post($url.'/upload',$data);
            if($rsp === false) throw  new Exception('文件上传异常');
            $rsp = json_decode($rsp,true);
            if(0 !== (int)$rsp['code']) throw new Exception($rsp['message']);
            return $rsp['content'];
        }catch (\Exception $e){
            throw $e;
        }
    }

    /**
     * 下载图片
     * @param $img_url
     * @param int $timeout
     * @return bool|string
     * @throws Exception
     */
    public function download($img_url,$timeout = 5){
        try{
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $img_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $img = curl_exec($ch);
            $err_no = curl_error($ch);
            if($err_no){
                $info  = curl_getinfo($ch);
                $info['errno'] = $err_no;
               throw new \Exception('图片下载失败'.json_encode($info,JSON_UNESCAPED_UNICODE) );
            }
            curl_close($ch);
            return $img;
        }catch(\Exception $e){
            throw $e;
        }
    }

    public function read($resource_id){
        try{
            $rsp = $this->file->post('/info?file_id='.$resource_id,[]);
            if(0 !== (int)$rsp['code'] || empty($rsp['content']) ) throw new Exception($rsp['message']);
            return $rsp['content'];
        }catch(\Exception $e){
            throw $e;
        }
    }

    private function _curl_post($url,$data){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        $err_no = curl_error($ch);
        if($err_no){
            $info  = curl_getinfo($ch);
            log_message('文件上传异常---msg='.json_encode($info) );
            return false;
        }
        curl_close($ch);
        return $response;
    }
}