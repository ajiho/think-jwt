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