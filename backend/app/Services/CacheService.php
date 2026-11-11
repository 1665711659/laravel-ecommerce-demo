<?php

namespace App\Services;

use App\Support\RedisKey;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * 通用 Redis 缓存组件：集中实现缓存穿透、击穿、雪崩三类问题的处理。
 *
 * 【面试备注】为什么不直接用 Cache::remember？
 * 1. Cache::remember 在缓存失效瞬间没有互斥，热点 key 会同时击穿到 DB；
 * 2. 这里把「空值缓存 + 逻辑过期时间随机偏移 + SETNX 互斥重建 + 校验 token 释放锁」
 *    拆开实现，便于讲清楚每一条防护措施的作用。
 */
class CacheService
{
    /** 重建锁超时（秒）：必须大于一次回源耗时，否则会误释放 */
    private const LOCK_TTL = 3;

    /** 拿不到锁时轮询等待次数，每次 100ms（只让一个线程回源） */
    private const LOCK_RETRIES = 5;

    /** 空值缓存 TTL（秒）：防穿透，但要比正常缓存短，避免商品新增后长时间查不到 */
    private const NULL_CACHE_TTL = 60;

    private static ?string $unlockScript = null;

    /**
     * 缓存读取 + 回源 + 写回。
     *
     * @param  int  $ttl  基础 TTL（秒）
     * @param  callable(): mixed  $resolver  回源闭包，返回 null 表示数据不存在
     */
    public function remember(string $key, int $ttl, callable $resolver): mixed
    {
        // 1) 先读缓存（空值缓存也是缓存，直接返回 null）
        $cached = Redis::get($key);
        if ($cached !== null) {
            return $this->unpack($cached);
        }

        $lockKey = RedisKey::cacheLock($key);
        $token = Str::uuid()->toString();

        // 2) SETNX 抢重建锁：同一时刻只有一个线程回源
        $locked = Redis::set($lockKey, $token, 'EX', self::LOCK_TTL, 'NX');

        if (! $locked) {
            // 3) 没抢到锁：短暂轮询等待缓存被别的线程写好，而不是直接打 DB
            for ($i = 0; $i < self::LOCK_RETRIES; $i++) {
                usleep(100_000);
                $cached = Redis::get($key);
                if ($cached !== null) {
                    return $this->unpack($cached);
                }
            }

            // 【面试备注】等待仍无结果时降级直查 DB，保证可用性优先；
            // 生产环境可改为返回兜底数据/排队提示，避免极端情况下 DB 压力放大。
            return $resolver();
        }

        try {
            // 4) 双重检查：等锁期间缓存可能已被前一个持锁线程写好
            $cached = Redis::get($key);
            if ($cached !== null) {
                return $this->unpack($cached);
            }

            $data = $resolver();

            if ($data === null) {
                // 缓存穿透：数据库不存在的 id 也写入空值，短 TTL 过期
                Redis::setex($key, self::NULL_CACHE_TTL, json_encode(['__null__' => true]));
                return null;
            }

            // 缓存雪崩：同一批商品同时过期会造成 DB 尖峰，给 TTL 加 0~20% 随机偏移
            $jitter = random_int(0, max(1, (int) round($ttl * 0.2)));
            Redis::setex(
                $key,
                $ttl + $jitter,
                json_encode(['data' => $data], JSON_UNESCAPED_UNICODE)
            );

            return $data;
        } finally {
            // 5) 校验 token 后删除，避免锁超时后误删别人的锁
            $this->releaseLock($lockKey, $token);
        }
    }

    /** 主动失效：先更新 DB 再调用本方法，见 GoodsService::update() */
    public function forget(string $key): void
    {
        Redis::del($key);
    }

    private function unpack(string $cached): mixed
    {
        $payload = json_decode($cached, true);

        if (! is_array($payload)) {
            return null;
        }

        if ($payload['__null__'] ?? false) {
            return null;
        }

        return $payload['data'] ?? null;
    }

    private function releaseLock(string $lockKey, string $token): void
    {
        self::$unlockScript ??= file_get_contents(base_path('resources/lua/unlock.lua'));

        Redis::eval(self::$unlockScript, 1, $lockKey, $token);
    }
}
