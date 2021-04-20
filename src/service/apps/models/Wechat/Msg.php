<?php

namespace Wechat;

class MsgModel
{
    /**
     * @param $params
     * @return string
     * 回复文本消息
     */
    public function text($params)
    {
        return '<Content><![CDATA[' . ($params['Content'] ?? '') . ']]></Content>';;
    }

    /**
     * @param $params
     * @return string
     * 回复图片消息
     */
    public function image($params)
    {
        return '<Image><MediaId><![CDATA[' . ($params['MediaId'] ?? '') . ']]></MediaId></Image>';
    }

    /**
     * @param $params
     * @return string
     * 回复语音消息
     */
    public function voice($params)
    {
        return '<Voice><MediaId><![CDATA[' . ($params['MediaId'] ?? '') . ']]></MediaId></Voice>';
    }

    /**
     * @param $params
     * @return string
     * 回复视频消息
     */
    public function video($params)
    {
        $xml = '<Video>';
        $xml .= '<MediaId><![CDATA[' . ($params['MediaId'] ?? '') . ']]></MediaId>';
        $xml .= '<Title><![CDATA[' . ($params['Title'] ?? '') . ']]></Title>';
        $xml .= '<Description><![CDATA[' . ($params['Description'] ?? '') . ']]></Description>';
        $xml .= '</Video>';
        return $xml;
    }

    /**
     * @param $params
     * @return string
     * 回复音乐消息
     */
    public function music($params)
    {
        $xml = '<Music>';
        $xml .= '<MediaId><![CDATA[' . ($params['MediaId'] ?? '') . ']]></MediaId>';
        $xml .= '<Title><![CDATA[' . ($params['Title'] ?? '') . ']]></Title>';
        $xml .= '<Description><![CDATA[' . ($params['Description'] ?? '') . ']]></Description>';
        $xml .= '<MusicUrl><![CDATA[' . ($params['MusicUrl'] ?? '') . ']]></MusicUrl>';
        $xml .= '<HQMusicUrl><![CDATA[' . ($params['HQMusicUrl'] ?? '') . ']]></HQMusicUrl>';
        $xml .= '<ThumbMediaId><![CDATA[' . ($params['ThumbMediaId'] ?? '') . ']]></ThumbMediaId>';
        $xml .= '</Music>';
        return $xml;
    }

    /**
     * @param $params
     * @return string
     * 回复音乐消息
     */
    public function news($params)
    {
        $xml = '<ArticleCount><![CDATA[' . ($params['ArticleCount'] ?? '') . ']]></ArticleCount>';
        $xml .= '<Articles>';
        $xml .= '<item>';
        $xml .= '<Title><![CDATA[' . ($params['Title'] ?? '') . ']]></Title>';
        $xml .= '<Description><![CDATA[' . ($params['Description'] ?? '') . ']]></Description>';
        $xml .= '<PicUrl><![CDATA[' . ($params['PicUrl'] ?? '') . ']]></PicUrl>';
        $xml .= '<Url><![CDATA[' . ($params['Url'] ?? '') . ']]></Url>';
        $xml .= '</item>';
        $xml .= '</Articles>';
        return $xml;
    }

}