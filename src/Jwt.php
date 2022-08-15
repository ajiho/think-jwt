<?php

namespace ajiho;

use DateTimeImmutable;
use DateTimeZone;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\ValidAt;
use Lcobucci\JWT\Validation\Constraint\IdentifiedBy;
use Lcobucci\JWT\Validation\Constraint\RelatedTo;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use think\facade\Config;
use think\facade\Cache;

class Jwt
{


    /**
     * 得到配置对象
     * @return Configuration
     */
    private static function getConfig()
    {
        return Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText(Config::get('jwt.jti')));
    }


    /**
     * @param array|string|integer $data 往token里面传入的数据
     * @return string|boolean 执行成功返回token字符串 ,失败返回false
     */
    public static function create($data)
    {
        try {
            $config = self::getConfig();
            $now = new DateTimeImmutable('now',  new DateTimeZone(Config::get('jwt.timezone')));
            return $config->builder()
                //主题
                ->relatedTo(Config::get('jwt.sub'))
                //签发人
                ->issuedBy(Config::get('jwt.iss'))
                //接收人  // canOnlyBeUsedBy方法在4.x中将会被移除被permittedFor替代
                ->permittedFor(...array_filter(Config::get('jwt.aud', []), 'is_string'))
                //唯一标志
                ->identifiedBy(Config::get('jwt.jti'))
                //签发时间
                ->issuedAt($now)
                //生效时间（立即生效:签发时间减一秒）
                ->canOnlyBeUsedAfter($now->modify('-1 second'))
                //过期时间
                ->expiresAt($now->modify("+" . Config::get('jwt.exp') . " second"))
                //存在token中的数据   // with方法在4.x中将会被移除被withClaim替代
                ->withClaim('_thinkJwt', json_encode($data, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE))
                //签名
                ->getToken($config->signer(), $config->signingKey())
                ->toString();
        } catch (\Exception $e) {
            return false;
        }

    }


    /**
     * 解析token
     * @param $token
     * @return array 返回格式如下 ['code' => xx, 'msg' => 'xx', 'data' => []]
     */
    public static function parse($token)
    {
        $config = self::getConfig();


        //注销token逻辑
        $delete_token = Cache::get(Config::get('jwt.delete_key')) ?: [];
        if (in_array($token, $delete_token)) {
            //token已被注销
            return ['code' => 10000, 'msg' => '该token已经注销', 'data' => []];
        }

        //token解析异常必须要用try catch抓取 当JWT不是字符串或无效时会抛出异常
        try {
            $token = $config->parser()->parse($token);
        } catch (\Exception $e) {
            return ['code' => 10001, 'msg' => 'token解码失败', 'data' => []];
        }

        //验证声明iss是否列为预期值
        $issued = new IssuedBy(Config::get('jwt.iss'));
        if (!$config->validator()->validate($token, $issued)) {
            return ['code' => 10002, 'msg' => '签发人验证失败', 'data' => []];
        }


        //验证声明是否aud包含预期值
        $auds = array_filter(Config::get('jwt.aud', []), 'is_string');
        foreach($auds as $aud){
            if (!$config->validator()->validate($token, new PermittedFor($aud))) {
                return ['code' => 10003, 'msg' => '接收人验证失败', 'data' => []];
            }
        }

        //验证声明是否jti与预期值匹配
        $jti = new IdentifiedBy(Config::get('jwt.jti'));
        if (!$config->validator()->validate($token, $jti)) {
            return ['code' => 10005, 'msg' => '编号验证失败', 'data' => []];
        }

        //验证声明是否sub与预期值匹配
        $sub = new RelatedTo(Config::get('jwt.sub'));
        if (!$config->validator()->validate($token, $sub)) {
            return ['code' => 10006, 'msg' => '主题验证失败', 'data' => []];
        }

        //验证令牌是否使用预期的签名者和密钥签名
        $sign = new SignedWith($config->signer(), $config->signingKey());
        if (!$config->validator()->validate($token, $sign)) {
            return ['code' => 10007, 'msg' => '签名密钥验证失败', 'data' => []];
        }

        //验证声明iat, nbf, 和exp(支持 leeway 配置)
        $timezone = new DateTimeZone(Config::get('jwt.timezone'));
        $now = new SystemClock($timezone);
        $valid_at = new ValidAt($now);
        if (!$config->validator()->validate($token, $valid_at)) {
            return ['code' => 10004, 'msg' => 'token已过期', 'data' => []];
        }

        //从token中取出存储的数据
        $data = json_decode($token->claims()->get('_thinkJwt'), true);
        return ['code' => 200, 'msg' => '', 'data' => $data];

    }


    /**
     * @param string $token 需要注销的token
     * @return void
     */
    public static function logout(string $token)
    {

        //取缓存中注销的token数组
        $delete_token = Cache::get(Config::get('jwt.delete_key')) ?: [];

        //把传递过来的token再存入缓存
        $delete_token[] = $token;

        //再次把新的缓存数据重新存入缓存中，缓存时间必须大于等于jwt生成时的有效期,否则注销不成功
        Cache::set(Config::get('jwt.delete_key'), $delete_token, Config::get('jwt.exp'));
    }
}