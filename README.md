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

think-jwt的使用方式非常简单,因为它不管你是如何传递token参数的，你可以选择Header、Cookie、Param，那都是你的自由,think-jwt只纯粹的提供3个核心静态方法(
create、parse、logout)和一个辅助静态方法(getRequestToken)

## getRequestToken

一般情况都是在请求头中通过`Authorization`字段传递token,
所以该方法就是快速获取请求头中`Authorization`的token值

> 注意

如果你是使用`apache`服务器的话，需要在tp6项目`public/.htaccess`重写文件中添加上一下重写规则，
否则可能接收不到请求头中`Authorization`的值。

~~~
#Authorization Headers
RewriteCond %{HTTP:Authorization} ^(.+)$
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
~~~

`.htaccess`完整内容如下

~~~
<IfModule mod_rewrite.c>
  Options +FollowSymlinks -Multiviews
  RewriteEngine On

  #Authorization Headers
  RewriteCond %{HTTP:Authorization} ^(.+)$
  RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteRule ^(.*)$ index.php/$1 [QSA,PT,L]
</IfModule>
~~~

## create

生成token

```php
use ajiho\Jwt;

$token = Jwt::create(100);
$token = Jwt::create('php是世界上最好的语言');
$token = Jwt::create(['id'=>100,'name'=>'jack']);
```

执行成功返回token字符串

~~~
eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczpcL1wvYXBpLnh4eC5jb20iLCJhdWQiOiJodHRwczpcL1wvd3d3Lnh4eC5jb20iLCJqdGkiOiIzZjJnNTdhOTJhYSIsImlhdCI6MTY1MTg1MTQ2MywibmJmIjoxNjUxODUxNDYyLCJleHAiOjE2NTE4NTg2NjMsIl90aGlua0p3dCI6IntcImlkXCI6MTAwLFwibmFtZVwiOlwiSmFja1wifSJ9.yVjHKxtZii3YfSwGMfFX_PIuBM5co-xpALx7p-Ld2_A
~~~

## parse

用于解析token，返回值是一个包含`code`,`msg`,`data`的数组,解析返回不同的状态码和说明,
当然了,这一部分逻辑推荐放中间件中去执行

~~~
['code' => xx, 'msg' => 'xx', 'data' => null]
~~~

状态码说明

| 状态码   | 说明                                                              |
|-------|-----------------------------------------------------------------|
| 200   | token解析成功,可以通过data字段得到token中存储的数据(生成token传入的什么类型的数据返回就是什么类型的数据) |
| 10000 | token已经被注销                                                      |
| 10001 | token解码失败                                                       |
| 10002 | 签发人验证失败                                                         |
| 10003 | 接收人验证失败                                                         |
| 10004 | token已过期                                                        |
| 10005 | 编号验证失败                                                          |
| 10006 | 主题验证失败                                                          |
| 10007 | 签名密钥验证失败                                                        |

下面是在中间件中验证token的示例代码:

```php

//生成token
$token = Jwt::create(100);// eyJ0eXAiOiJKV1...

/**
 * 验证token的中间件
 * 
 * @param \think\Request $request
 * @param \Closure $next
 * @return Response
 */
public function handle($request, \Closure $next)
{
    // 这里我们直接解析上面生成的token
    // $parseResult = Jwt::parse('eyJ0eXAiOiJKV1...');

    //您也可以不传递它会默认解析请求头中的HTTP_AUTHORIZATION字段(v2.0.3+)
    $parseResult = Jwt::parse();
    
    if ($parseResult['code'] !== 200) {//不等于200等于解析失败
        
        // 您可以在这里笼统的直接返回token解析失败,然后给前端返回一个401
        return json(['code' => 401, 'msg' => 'token解析失败', 'data' => []]);
       
        //如果您有特殊需求，可以根据上面的状态码获取具体的错误消息，比如可以判断token是否已经过期等
        //...
    }
    
    //验证通过,将得到的用户id,放到请求信息中去,方便后续使用
    $request->user_id = $parseResult['data'];//100

    return $next($request);
}
```

然后你在任意地方只需要依赖注入request请求对象，就能得到登录的用户id

```php
//获取当前登录用户的信息
public function userInfo(Request $request)
{
    
    $user = User::where('status', 1)
        ->find($request->user_id);
    
    return json($user);

}
```

## logout

Jwt的token一经签发是它是无法被注销的，所以只能通过服务端来进行判断(结果到这里又变成有状态的了),这里
是通过把要注销的token存储到缓存中，所以配置文件`jwt.php`中它有个`delete_key`配置就是用来实现注销功能的，默认
缓存的key是`delete_token`,如果和你的业务发生冲突，你可以自行更改。
这里的的缓存用的是tp6框架自带的缓存`cache`方法


> 代码示例

```php
//退出登录
public function logout()
{
    //Jwt::logout('eyJ0eXAiOiJKV1...');
    //您也可以不传递,它会默认注销请求头中HTTP_AUTHORIZATION传递过来的token(v2.0.3+)
    Jwt::logout();
    return json(['code' => 200, 'msg' => '用户退出成功', 'data' => []]);
}
```

此时客户端继续使用已经被注销的token来解析就会提示token已被注销,它过不了中间件的验证

```php
/**
 * 验证token中间件
 * 
 * @param \think\Request $request
 * @param \Closure $next
 * @return Response
 */
public function handle($request, \Closure $next)
{
    $parseResult = Jwt::parse('eyJ0eXAiOiJKV1...');

    if ($parseResult['code'] !== 200) {
       dd($parseResult);//['code' => 10000, 'msg' => 'token已经被注销', 'data' => []]，因此被注销后的token它是无法继续向下执行的。
       return json(['code' => 401, 'msg' => 'token解析失败', 'data' => []]);
    }
    
    //验证通过,将得到的用户id,放到请求信息中去,方便后续使用
    $request->user_id = $parseResult['data'];//100

    return $next($request);
}
```

