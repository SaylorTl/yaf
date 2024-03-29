<?php
/**
 * Wechat_Prpcrypt class
 *
 * 提供接收和推送给公众平台消息的加解密接口.
 */
class Wechat_Prpcrypt
{
    public $key;

    public function __construct($k)
    {
        $this->key = base64_decode($k . "=");
    }

    /**
     * 对明文进行加密
     * @param string 需要加密的明文
     * @param string
     * @return array 加密后的密文
     */
    public function encrypt($text, $corpid)
    {
        try {
            //获得16位随机字符串，填充到明文之前
            $random = $this->getRandomStr();
            $text = $random . pack("N", strlen($text)) . $text . $corpid;
            //使用自定义的填充方式对明文进行补位填充
            $pkc_encoder = new Wechat_PKCS7Encoder;
            $text = $pkc_encoder->encode($text);
            //加密
            $encrypted = openssl_encrypt($text, 'AES-256-CBC', $this->key, OPENSSL_ZERO_PADDING, substr($this->key, 0,16));
            //使用BASE64对加密后的字符串进行编码
            return array(Wechat_ErrorCode::$OK, $encrypted);
        } catch (Exception $e) {
            return array(Wechat_ErrorCode::$EncryptAESError, null);
        }
    }

    /**
     * 对密文进行解密
     * @param string 需要解密的密文
     * @return array|string 解密得到的明文
     */
    public function decrypt($encrypted, $corpid)
    {

        try {
            //解密
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $this->key, OPENSSL_ZERO_PADDING, substr($this->key, 0,16));
        } catch (Exception $e) {
            return array(Wechat_ErrorCode::$DecryptAESError, null);
        }

        try {
            //去除补位字符
            $pkc_encoder = new Wechat_PKCS7Encoder;
            $result = $pkc_encoder->decode($decrypted);
            //去除16位随机字符串,网络字节序和AppId
            if (strlen($result) < 16)
                return "";
            $content = substr($result, 16, strlen($result));
            $len_list = unpack("N", substr($content, 0, 4));
            $xml_len = $len_list[1];
            $xml_content = substr($content, 4, $xml_len);
            $from_corpid = substr($content, $xml_len + 4);
        } catch (Exception $e) {
            print $e;
            return array(Wechat_ErrorCode::$IllegalBuffer, null);
        }
        if ($from_corpid != $corpid)
            return array(Wechat_ErrorCode::$DecryptAESError, null);
        return array(0, $xml_content);
    }


    /**
     * 随机生成16位字符串
     * @return string 生成的字符串
     */
    function getRandomStr()
    {
        $str = "";
        $str_pol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($str_pol) - 1;
        for ($i = 0; $i < 16; $i++) {
            $str .= $str_pol[mt_rand(0, $max)];
        }
        return $str;

    }

}