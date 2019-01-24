<?php

namespace App\Http\Controllers;

use App\Http\Models\Sso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class SsoController extends Controller
{

    /**
     * 种当前网站的cookie
     * @param $appid
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function iframe($appid, Request $request)
    {

        $app = self::app($appid, $_SERVER['HTTP_REFERER']);
        if(!$app){
            return response()->json('fail', 405);
        }
        header('P3P: CP="CAO PSA OUR"');
        if(isset($_COOKIE[self::COOKIE_KEY]) && $_COOKIE[self::COOKIE_KEY]){
            $data = [
                'ssoid' => $_COOKIE[self::COOKIE_KEY],
                'appid' => $_COOKIE['appid'] ?? '',
                'uid'   => $_COOKIE['uid'] ?? 0,
            ];
            $sign = self::authCode(json_encode($data), 'ENCODE', self::SALT, self::SIGN_EXPIRE);
        }else{
            $sign = self::authCode('', 'ENCODE', self::SALT, self::SIGN_EXPIRE);
        }
        header('Location:'.$app['set_cookie_url'].'?sign='.urlencode($sign));
    }

    /**
     * 获取sso的cookie数据
     * @param $token
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSsoId($token, Request $request)
    {
        $ssoid = self::authCode(urldecode($token), 'DECODE', self::SALT, self::SIGN_EXPIRE);
        return self::responseJson($ssoid);
    }

    /**
     * jsonp形式登录
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function jsonp(Request $request)
    {
        header('P3P: CP="CAO PSA OUR"');
        header('Content-Type: application/json');
        $func = self::removeXSS($request->input('callback'));
        $account = self::removeXSS($request->input('a'));
        $account = base64_decode($account);
        $pwd = self::removeXSS($request->input('p'));
        $pwd = base64_decode($pwd);
        $appid = self::removeXSS($request->input('id'));
        if(!$account && !$pwd){
            return self::responseJson(false,'10002');
        }
        if(!$appid){
            return self::responseJson(false,'10001');
        }
        $app = self::app($appid, $_SERVER['HTTP_REFERER']);
        if(!$app){
            return self::responseJson(false,'10001');
        }
        $method  = $app['method'];
        $codeStr = $app['code_str'];
        $code    = $app['code'];
        $dataStr = $app['data_str'];
        $accountStr = $app['login_account_str'];
        $pwdStr = $app['login_pwd_str'];
        $uidStr = $app['uid_str'];
        $res = '';
        switch ($method){
            case 'GET':
                $url = rtrim($app['login_url'],'/').'/?'.$accountStr.'='.$account.'&'.$pwdStr.'='.$pwd;
                $res = self::httpGet($url);
                break;
            case 'POST':
                $url = $app['login_url'];
                $res = self::httpPost($url, [$accountStr => $account, $pwdStr => $pwd]);
                break;
        }
        if(!$res){
            return self::responseJson(false,'10002');
        }
        if($res = json_decode($res, true)){
            if($res[$codeStr] != $code){
                echo $func.'(\''.json_encode(['res'=>false]).'\')';exit;
            }

            $this->setCookie($res[$dataStr], $uidStr, $appid);//种cookie

            echo $func.'(\''.json_encode(['res'=>$res[$dataStr]]).'\')';exit;
        }
        echo $func.'(\''.json_encode(['res'=>false]).'\')';exit;
    }


    public function quit(Request $request)
    {
        header('Content-Type: application/json');
        $func = self::removeXSS($request->input('callback'));
        $appid    = $_COOKIE['appid'] ?? '';
        $uid      = $_COOKIE['uid'] ?? '';

        $app = self::app($appid, $_SERVER['HTTP_REFERER']);
        if(!$app || !$uid){
            echo $func.'(\''.json_encode(['res'=>false]).'\')';exit;
        }
        setcookie(self::COOKIE_KEY, '', time() -3600);
        setcookie('appid', '', time() -3600);
        setcookie('uid', '', time() -3600);
        $this->delRedis($appid, $uid);
        echo $func.'(\''.json_encode(['res'=>true]).'\')';exit;
    }

    public function auth(Request $request)
    {
        $appid = $request->input('appid');
        $sign  = $request->input('sign');
        $time  = $request->input('time');
        $uid   = $request->input('uid');
        $redisKey = $request->input('ssoid');
        $bindKey = $this->bindKey($appid, $uid);
        if(!$appid || !$sign || !$redisKey){
            return self::responseJson(false, '10005');
        }

        $appsecrt = $this->getAppsecrt($appid);

        if(!$appsecrt){
            return self::responseJson(false, '10004');
        }

        if(md5($appsecrt.$time) != $sign){
            return self::responseJson(false, '10003');
        }
        if(Redis::get($bindKey) != $redisKey){
            return self::responseJson(false, '10008');
        }
        $res = Redis::get($redisKey);
        if(!$res){
            return self::responseJson(false, '10006');
        }
        $this->setRedis($bindKey, $redisKey, $res);//延长过期时间
        return self::responseJson(json_decode($res, true));
    }

    private function setCookie($data, $uidStr, $appid)
    {
        $time = time();
        $uid = $data[$uidStr];
        $data['_sso_time'] = $time;
        $dataStr = json_encode($data);
        list($redisKey, $bindKey)= $this->redisKey($appid, $uid, $time);
        if($this->setRedis($bindKey, $redisKey, $dataStr)){
            setcookie(self::COOKIE_KEY, $redisKey, $time + self::COOKIE_EXPIRE);
            setcookie('appid', $appid, $time + self::COOKIE_EXPIRE);
            setcookie('uid', $uid, $time + self::COOKIE_EXPIRE);
        }
    }

    private function redisKey($appid, $uid, $time)
    {
        $redisKey = md5(self::REDIS_PRE.$appid.$uid.$time);
        $bindKey = $this->bindKey($appid, $uid);
        return [$redisKey, $bindKey];
    }

    private function bindKey($appid, $uid)
    {
       return self::REDIS_PRE.$appid.$uid;
    }

    private function setRedis($bindKey, $redisKey, $data)
    {
        if(Redis::exists($bindKey)){
            Redis::del($bindKey);
        }
        $bindKey  =  Redis::setex($bindKey, self::REDIS_EXPIRE, $redisKey);
        $bindData = Redis::setex($redisKey, self::REDIS_EXPIRE, $data);
        if($bindKey && $bindData){
            return true;
        }else{
            return false;
        }
    }

    private function delRedis($appid, $uid)
    {
        $bindKey = $this->bindKey($appid, $uid);
        $redisKey = Redis::get($bindKey);
        Redis::del($bindKey);//删除绑定key
        Redis::del($redisKey);//删除登录信息
    }

    private function getRandomStr($len = 10)
    {
        $strs="QWERTYUIOPASDFGHJKLZXCVBNM1234567890qwertyuiopasdfghjklzxcvbnm";
        $name=substr(str_shuffle($strs),mt_rand(0,strlen($strs)-$len-1),$len);
        echo $name;
    }

    private function getAppsecrt($appid)
    {
        if($sso = self::sso($appid)){
            return $sso['appsecret'] ?? '';
        }
        return false;
    }
}
