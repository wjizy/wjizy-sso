<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    const JSOP_FUNC = 'callback';
    const REDIS_EXPIRE = 21600;// 6个小时
    const COOKIE_EXPIRE = 31536000;// 365天
    const APPSECRET_EXPIRE = 604800; //7天
    const COOKIE_KEY = 'ssoid';
    const APPID_PRE = 'appid:';
    const REDIS_PRE = 'wjizy';
    const SALT = 'qwertuasdg';
    const SIGN_EXPIRE = 10;
    /**
     * 统一返回结构
     * @param $data
     * @param int $errorCode
     * @param string $errorMsg
     * @return \Illuminate\Http\JsonResponse
     */
    public static function responseJson($data, $errorCode = 0, $errorMsg = '')
    {
        return response()->json([
            "data"       => $data,
            "code"       => intval($errorCode),
            "msg"        => empty($errorMsg) && $errorCode !=0 ? self::errorCode($errorCode) : $errorMsg,
            "serverTime" => time(),
        ]);
    }

    public static function errorCode($number)
    {
        $code = [
            '10001' => 'appid不存在',
            '10002' => '账号密码错误',
            '10003' => '验证签名失败',
            '10004' => 'Appsecrt不存在',
            '10005' => '参数错误',
            '10006' => '登录信息不存在或已过期',
            '10007' => '姿势不对~',
            '10008' => '账号不对应',
            '10009' => 'sign错误或者已过期',
        ];
        return isset($code[$number]) ? $code[$number] : '未知错误';
    }

    public  static function httpGet($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public static function httpPost($url, $param = array(),$type = 'application/json')
    {
        $httph = curl_init($url);
        switch ($type){
            case 'application/json':
                $data = json_encode($param);
                curl_setopt($httph, CURLOPT_HTTPHEADER, array('Content-Type: '.$type, 'Content-Length: ' . strlen($data)));
                break;
            default:
                $data = http_build_query($param);
        }
        curl_setopt($httph, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($httph, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($httph, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($httph, CURLOPT_POST, 1);
        curl_setopt($httph, CURLOPT_POSTFIELDS, $data);
        curl_setopt($httph, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($httph, CURLOPT_CONNECTTIMEOUT , 10);
        curl_setopt($httph, CURLOPT_TIMEOUT, 10);
        $rst = curl_exec($httph);
        curl_close($httph);

        return $rst;
    }

    protected static function sso($appid = '')
    {
        $app = _getSSOApp();
        if(is_array($app) && count($app) > 0){
            if($appid && isset($app[$appid])){
                return $app[$appid];
            }
            return $app;
        }
        return [];
    }
    protected static function app($appid, $referer)
    {
       $app = self::sso();
       if(!isset($app[$appid])){
           return null;
       }
       $url = parse_url($referer);
       $hostname = $url['host'];
       $res = null;
       if(in_array($hostname, $app[$appid]['domain'])){
            return $app[$appid];
       }
       return $res;
    }

    //字符串解密加密
    protected static function authCode($string, $operation = 'DECODE', $key = '', $expiry = 0)
    {

        $ckey_length = 4;   // 随机密钥长度 取值 0-32;
        // 加入随机密钥，可以令密文无任何规律，即便是原文和密钥完全相同，加密结果也会每次不同，增大破解难度。
        // 取值越大，密文变动规律越大，密文变化 = 16 的 $ckey_length 次方
        // 当此值为 0 时，则不产生随机密钥

        $key = md5($key ? $key : "wjizy");
        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';

        $cryptkey = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);

        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
        $string_length = strlen($string);

        $result = '';
        $box = range(0, 255);

        $rndkey = array();
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        if ($operation == 'DECODE') {
            if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            return $keyc . str_replace('=', '', base64_encode($result));
        }
    }

    protected static function removeXSS( $val ) {
        $val = preg_replace('/([\x00-\x08,\x0b-\x0c,\x0e-\x19])/' , '' , $val);

        $search = 'abcdefghijklmnopqrstuvwxyz';
        $search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $search .= '1234567890!@#$%^&*()';
        $search .= '~`";:?+/={}[]-_|\'\\';
        for ($i = 0 ; $i < strlen($search) ; $i ++) {
            $val = preg_replace('/(&#[xX]0{0,8}' . dechex(ord($search[$i])) . ';?)/i' , $search[$i] , $val);
            $val = preg_replace('/(&#0{0,8}' . ord($search[$i]) . ';?)/' , $search[$i] , $val);
        }
        $ra1 = [ 'javascript' , 'vbscript' , 'expression' , 'applet' , 'meta' , 'xml' , 'blink' , 'link' , 'style' , 'script' , 'embed' , 'object' , 'iframe' , 'frame' , 'frameset' , 'ilayer' , 'layer' , 'bgsound' , 'title' , 'base' ];
        $ra2 = [ 'onabort' , 'onactivate' , 'onafterprint' , 'onafterupdate' , 'onbeforeactivate' , 'onbeforecopy' , 'onbeforecut' , 'onbeforedeactivate' , 'onbeforeeditfocus' , 'onbeforepaste' , 'onbeforeprint' , 'onbeforeunload' , 'onbeforeupdate' , 'onblur' , 'onbounce' , 'oncellchange' , 'onchange' , 'onclick' , 'oncontextmenu' , 'oncontrolselect' , 'oncopy' , 'oncut' , 'ondataavailable' , 'ondatasetchanged' , 'ondatasetcomplete' , 'ondblclick' , 'ondeactivate' , 'ondrag' , 'ondragend' , 'ondragenter' , 'ondragleave' , 'ondragover' , 'ondragstart' , 'ondrop' , 'onerror' , 'onerrorupdate' , 'onfilterchange' , 'onfinish' , 'onfocus' , 'onfocusin' , 'onfocusout' , 'onhelp' , 'onkeydown' , 'onkeypress' , 'onkeyup' , 'onlayoutcomplete' , 'onload' , 'onlosecapture' , 'onmousedown' , 'onmouseenter' , 'onmouseleave' , 'onmousemove' , 'onmouseout' , 'onmouseover' , 'onmouseup' , 'onmousewheel' , 'onmove' , 'onmoveend' , 'onmovestart' , 'onpaste' , 'onpropertychange' , 'onreadystatechange' , 'onreset' , 'onresize' , 'onresizeend' , 'onresizestart' , 'onrowenter' , 'onrowexit' , 'onrowsdelete' , 'onrowsinserted' , 'onscroll' , 'onselect' , 'onselectionchange' , 'onselectstart' , 'onstart' , 'onstop' , 'onsubmit' , 'onunload' ];
        $ra  = array_merge($ra1 , $ra2);

        $found = true;
        while ( $found == true ) {
            $val_before = $val;
            for ($i = 0 ; $i < sizeof($ra) ; $i ++) {
                $pattern = '/';
                for ($j = 0 ; $j < strlen($ra[$i]) ; $j ++) {
                    if ( $j > 0 ) {
                        $pattern .= '(';
                        $pattern .= '(&#[xX]0{0,8}([9ab]);)';
                        $pattern .= '|';
                        $pattern .= '|(&#0{0,8}([9|10|13]);)';
                        $pattern .= ')*';
                    }
                    $pattern .= $ra[$i][$j];
                }
                $pattern     .= '/i';
                $replacement = substr($ra[$i] , 0 , 2) . '<x>' . substr($ra[$i] , 2);
                $val         = preg_replace($pattern , $replacement , $val);
                if ( $val_before == $val ) {
                    $found = false;
                }
            }
        }
        return htmlentities($val);
    }
}
