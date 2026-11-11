<?php

namespace App\Support;

/**
 * Redis key 统一收口，避免各 Service 拼字符串导致 key 冲突/拼写错误。
 * 生产环境建议再拼上环境前缀（dev:/test:/prod:），并禁止在 Redis 里放大 key。
 */
final class RedisKey
{
    public const PREFIX = 'demo:';

    public static function goodsDetail(int $goodsId): string
    {
        return self::PREFIX."goods:detail:{$goodsId}";
    }

    public static function goodsList(): string
    {
        return self::PREFIX.'goods:list';
    }

    public static function seckillStock(int $seckillId): string
    {
        return self::PREFIX."seckill:stock:{$seckillId}";
    }

    /** 一人一单标记：Set 结构，member = userId */
    public static function seckillUsers(int $seckillId): string
    {
        return self::PREFIX."seckill:users:{$seckillId}";
    }

    /** 秒杀令牌桶：按活动维度全局限流 */
    public static function seckillRate(int $seckillId): string
    {
        return self::PREFIX."seckill:rate:{$seckillId}";
    }

    public static function orderIdempotent(int $userId, string $requestId): string
    {
        return self::PREFIX."order:idempotent:{$userId}:{$requestId}";
    }

    public static function cacheLock(string $cacheKey): string
    {
        return self::PREFIX."lock:cache:{$cacheKey}";
    }
}
