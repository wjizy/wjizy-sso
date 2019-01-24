# wjizy-sso

此项目是一个web-sso项目，适合多个不同主域名需要同一个登录态情况

### 时序图

- 登录

![avatar](https://github.com/wjizy/wjizy-sso/blob/master/tests/login.png)

- 退出

![image](https://github.com/wjizy/wjizy-sso/blob/master/tests/quit.png)

- 验证

![image](https://github.com/wjizy/wjizy-sso/blob/master/tests/auth.png)

### 文件说明

> app/sso.php

```javascript

function _getSSOApp()
{
    return [
        'd6fkQu7x8LhB3KQH' => [// appid
                'appsecret' => 'WKAIGIBg1vHNvk9G',//appsecret
                'domain'    =>['127.0.0.1'],//接入sso的网站域名，可填多个
                'login_url'=>'http://127.0.0.1:9098/index.php',//账号服务登录接
                'set_cookie_url'=>'http://127.0.0.1:9098/set.php',//设置登录cookie链接
                'login_account_str' => 'name',//账户名称字段
                'login_pwd_str'     => 'pwd',//密码字段
                'method'    => 'GET',//请求方式
                'code_str'  => 'code',//返回码字段
                'code'      => 0,//登录成功的返回码
                'data_str'  => 'data',//返回数据字段
                'uid_str'   => 'uid',//账号ID,data中的账号ID字段
        ]
    ];
}
```

> .env.example

环境变量字段

搭建服务器时，复制一份并修改名字为env，不是.env。里面的值根据实际来填
```javascript
APP_NAME=web-sso
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
APP_TIMEZONE=UTC

LOG_CHANNEL=stack
LOG_SLACK_WEBHOOK_URL=

#DB_CONNECTION=mysql
#DB_HOST=127.0.0.1
#DB_PORT=3306
#DB_DATABASE=web-sso
#DB_USERNAME=root
#DB_PASSWORD=root

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DATABASE=0
REDIS_PASSWORD=null

CACHE_DRIVER=file
QUEUE_CONNECTION=sync

```

### 使用

- （用的lumen框架）配置好sso环境,设置app/sso.php里面的参数，假设域名是 www.sso.com
- 需要接入sso的网站引入sso.js

```html
<!DOCTYPE html>
<html>
<head>
	<title></title>
</head>
<body>
	<button id="login">登录</button>
	<button id="quit">退出</button>
	<script src="https://cdn.bootcss.com/jquery/3.3.1/jquery.min.js"></script>
	<script src="http://www.sso.com/js/sso.js"></script>
	<script type="text/javascript">
		name = '你好';
		password = '你好';
		appid = 'd6fkQu7x8LhB3KQH';
		domain = 'http://www.sso.com';
		$_sso.init(appid, domain);
		$(function(){
			$('#login').on('click',function(){
				$_sso.login(name, password, appid).handle = function(res){
					console.log('login',res);
					console.log('login',res.res);
				}
			})

			$('#quit').on('click',function(){
				$_sso.quit().handle = function(res){
					console.log('quit', res);
					console.log('login',res.res);
				}
			});
		});
	</script>
</body>
</html>


```

- 验证cookie有效性代码
```php
  function httpPost($url, $param = array(),$type = 'application/json')
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
$appid = 'd6fkQu7x8LhB3KQH';
$appsecret = 'WKAIGIBg1vHNvk9G';
$time = time();
$uid = $_COOKIE['uid'] ?? 0;
$sign = md5($appsecret.$time);//签名
$ssoid = $_COOKIE['ssoid'] ?? '';
var_dump(httpPost('http://www.sso.com/auth',['uid'=>$uid,'appid'=>$appid,'sign'=>$sign,'time'=>$time,'ssoid'=>$ssoid]));
```