<?php

class TgParser
{
    public $url;

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function getHtml()
    {
        $html = false;


        $url = $this->url;
        if (!strstr($url, 't.me/')) return '<html><body></body></html>';
        if (!strstr($url, 't.me/s/')) $url = str_replace('t.me/', 't.me/s/', $url);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $html = curl_exec($ch);
        curl_close($ch);

        return $html;
    }

    public static function randomString($length = 20)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
