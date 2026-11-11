<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * 基于 Redis + Lua 的令牌桶限流。
 *
 * 【面试备注】为什么用令牌桶而不是固定窗口计数器？
 * 固定窗口在窗口切换点会出现 2 倍突发流量（临界问题）；
 * 令牌桶允许一定突发（桶容量），同时用 rate 控制长期平均速率。
 * 这里把「读时间、补令牌、扣令牌、写回」放进一个 Lua，避免 GET 后再 SET 的竞态。
 */
class RateLimitService
{
    private static ?string $script = null;

    /**
     * @param  int  $capacity  桶容量（瞬时最大并发）
     * @param  float  $rate  每秒补充的令牌数（长期 QPS 上限）
     * @return bool true=放行，false=限流
     */
    public function attempt(string $key, int $capacity, float $rate): bool
    {
        self::$script ??= file_get_contents(base_path('resources/lua/rate_limit.lua'));

        $result = Redis::eval(self::$script, 1, $key, $capacity, $rate);

        return (int) $result === 1;
    }
}
