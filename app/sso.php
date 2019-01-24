<?php
function _getSSOApp()
{
    return [
        'd6fkQu7x8LhB3KQH' => [// appid
                'appsecret' => 'WKAIGIBg1vHNvk9G',//appsecret
                'domain'    => ['127.0.0.1'],//接入sso的网站域名
                'login_url' => 'http://127.0.0.1:9098/index.php',//账号服务登录链接
                'set_cookie_url' => 'http://127.0.0.1:9098/set.php',//设置登录cookie链接
                'login_account_str' => 'name',//账户名称字段
                'login_pwd_str'     => 'pwd',//密码字段
                'method'    => 'GET',//请求方式
                'code_str'  => 'code',//返回码字段
                'code'      => 0,//登录成功的返回码
                'data_str'  => 'data',//数据字段
                'uid_str'   => 'uid',//账号ID,data中的账号ID字段
        ]
    ];
}
