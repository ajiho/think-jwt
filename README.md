
# think-jwt

基于[lcobucci/jwt](https://packagist.org/packages/lcobucci/jwt)封装的一个jwt工具包,在前后端分离时
它非常有用。


# 安装

~~~
composer require ajiho/think-jwt
~~~

# 配置

/config/jwt.php

```php
<?php
return [
    //注销token缓存key
    'delete_key' => 'delete_token',
    //时区
    'timezone' => 'Asia/Shanghai',
    //编号
    'jti' => '4f1g23a12aa',
    //签名密钥
    'sign' => 'a4693602cbb7a',
    //签发人
    'iss' => 'http://example.cn',
    //接收人
    'aud' => [
        'http://example.com',
        'http://example.org',
        'http://example.top',
    ],
    //主题
    'sub' => '100',
    //有效期(默认两个小时)  单位:秒
    'exp' => 3600 * 2
];
```

# 使用
think-jwt的使用方式非常简单,因为它不管你是如何传递token参数的，你可以选择Header、Cookie、Param，那都是你的自由
think-jwt只纯粹的提供3个静态方法`create()`,`parse()`,`logout()`,分别是，解析，和注销。

## create

> 示例：通过用户id生成token

```php
$token = \ajiho\Jwt::create(100);
```
得到如下结果:
~~~
eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczpcL1wvYXBpLnh4eC5jb20iLCJhdWQiOiJodHRwczpcL1wvd3d3Lnh4eC5jb20iLCJqdGkiOiIzZjJnNTdhOTJhYSIsImlhdCI6MTY1MTg1MTQ2MywibmJmIjoxNjUxODUxNDYyLCJleHAiOjE2NTE4NTg2NjMsIl90aGlua0p3dCI6IntcImlkXCI6MTAwLFwibmFtZVwiOlwiSmFja1wifSJ9.yVjHKxtZii3YfSwGMfFX_PIuBM5co-xpALx7p-Ld2_A
~~~



## parse

用于解析token，返回值是一个包含code,msg,data的数组,解析返回不同的状态码和说明,当然了,这一部分逻辑推荐放中间件中去执行
~~~
['code' => xx, 'msg' => 'xx', 'data' => []]
~~~
### 状态码说明

| 状态码 | 说明  |
|--|--|
| 200 | token解析成功,可以通过data字段得到token中存储的数据 |
| 10000 | token已经被注销 |
| 10001 | token解码失败 |
| 10002 | 签发人验证失败 |
| 10003 | 接收人验证失败 |
| 10004 | token已过期 |
| 10005 | 编号验证失败 |
| 10006 | 主题验证失败 |
| 10007 | 签名密钥验证失败 |



> 示例:整数

```php
//生成token
$token = \ajiho\Jwt::create(100);
//解析token  extract函数可以把数组分别作为变量解析出来
extract(\ajiho\Jwt::parse($token));
// dd($code,$msg,$data);//200 '' 100
if($code == 200){
    //解析成功
    dd($data); //100
}else{
    //失败，可以直接给前端返回错误信息了,或者你还可以根据code来做特殊处理
    dd($msg);
}
```
> 示例:字符串


```php
//生成token
$token = \ajiho\Jwt::create('php是世界上最好的语言');
//解析token  extract函数可以把数组分别作为变量解析出来
extract(\ajiho\Jwt::parse($token));
if($code == 200){
    //解析成功
    dd($data); //"php是世界上最好的语言"
}else{
    //失败，可以直接给前端返回错误信息了,或者你还可以根据code来做特殊处理
    dd($msg);
}
```

> 示例:数组

```php
//生成token
$token = \ajiho\Jwt::create(['id'=>100,'name'=>'jack']);
//解析token  extract函数可以把数组分别作为变量解析出来
extract(\ajiho\Jwt::parse($token));
if($code == 200){
    //解析成功
    dd($data); // ['id'=>100,'name'=>'jack']
}else{
    //失败，可以直接给前端返回错误信息了,或者你还可以根据code来做特殊处理
    dd($msg);
}
```


## logout

Jwt的token一经签发是它是无法被注销的，所以只能通过服务端来进行判断(结果到这里又变成有状态的了),这里
是通过把要注销的token存储到缓存中，所以配置文件`jwt.php`中它有个`delete_key`配置就是用来实现注销功能的，默认
缓存的key是`delete_token`,如果和你的业务发生冲突，你可以自行更改。 这里的的缓存用的是tp6框架自带
的缓存`cache`方法


> 代码示例

```php
//生成token
$token = \ajiho\Jwt::create(['id'=>100,'name'=>'jack']);


//注销token
\ajiho\Jwt::logout($token);

//解析token  extract函数可以把数组分别作为变量解析出来
extract(\ajiho\Jwt::parse($token));
if($code == 200){
    dd($data);
}else{
    dd($code,$msg);//10000 该token已经注销
}
```
