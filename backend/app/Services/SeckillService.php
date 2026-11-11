<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Seckill;
use App\Models\User;
use App\Repositories\OrderRepository;
use App\Repositories\SeckillRepository;
use App\Support\ErrorCode;
use App\Support\RedisKey;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class SeckillService
{
    /**
     * 令牌桶参数：单活动全局 200 个/秒、桶容量 200。
     * 即每秒最多放行 200 个请求进入 Redis 预扣，超出的直接 429，
     * 避免瞬时流量全部打到 Redis/MySQL（真实场景按活动热度调小）。
     */
    private const BUCKET_CAPACITY = 200;
    private const BUCKET_RATE = 200.0;

    private static ?string $stockScript = null;
    private static ?string $rollbackScript = null;

    public function __construct(
        private readonly SeckillRepository $seckillRepository,
        private readonly OrderRepository $orderRepository,
        private readonly RateLimitService $rateLimitService,
    ) {
    }

    public function activities(): array
    {
        return $this->seckillRepository->activeList()
            ->map(fn (Seckill $seckill) => $this->formatSeckill($seckill, $this->remainingStock($seckill)))
            ->all();
    }

    public function detail(int $id): array
    {
        $seckill = $this->seckillRepository->findWithGoods($id);
        if (! $seckill) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '秒杀活动不存在', 404);
        }

        return $this->formatSeckill($seckill, $this->remainingStock($seckill));
    }

    /**
     * 秒杀主链路：
     * 请求 -> 令牌桶限流 -> Redis Lua 原子预扣(校验库存 + 扣减 + 一人一单)
     *      -> 抢到才创建订单(MySQL 条件更新兜底) -> 等待支付
     * 抢失败（售罄/重复）直接返回，不碰数据库。
     */
    public function buy(User $user, int $seckillId): array
    {
        $seckill = $this->seckillRepository->findWithGoods($seckillId);
        if (! $seckill) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '秒杀活动不存在', 404);
        }

        $now = now();
        if ($seckill->status !== Seckill::STATUS_ON || $now->greaterThan($seckill->end_time)) {
            throw new BusinessException(ErrorCode::SECKILL_ENDED);
        }
        if ($now->lessThan($seckill->start_time)) {
            throw new BusinessException(ErrorCode::SECKILL_NOT_START);
        }

        // 1) 令牌桶限流：Immediate fail，抢不到令牌直接提示，保护后面两层
        if (! $this->rateLimitService->attempt(
            RedisKey::seckillRate($seckillId),
            self::BUCKET_CAPACITY,
            self::BUCKET_RATE
        )) {
            throw new BusinessException(ErrorCode::RATE_LIMITED, null, 429);
        }

        // 2) Redis Lua 原子预扣：库存校验、扣减、一人一单标记一次完成
        $this->ensureStockInitialized($seckill);
        $result = $this->runStockScript($seckill, $user->id);

        if ($result === -1) {
            // 极端情况：key 刚好不存在（例如 Redis 重启），初始化后重试一次
            $this->ensureStockInitialized($seckill);
            $result = $this->runStockScript($seckill, $user->id);
        }

        if ($result === 2) {
            throw new BusinessException(ErrorCode::SECKILL_DUPLICATE);
        }
        if ($result !== 1) {
            // 3 = 售罄；-1 仍失败也按售罄处理，不打 DB
            throw new BusinessException(ErrorCode::SECKILL_SOLD_OUT);
        }

        // 3) 抢到了才落库：DB 条件更新作为最终兜底
        try {
            $order = DB::transaction(function () use ($user, $seckill) {
                $affected = $this->seckillRepository->decrementStock($seckill->id);
                if ($affected === 0) {
                    // Redis 显示有库存但 DB 已扣完（极端不一致），抛异常回滚 Redis 预扣
                    throw new BusinessException(ErrorCode::SECKILL_SOLD_OUT);
                }

                $order = $this->orderRepository->create([
                    'order_no' => 'SK'.now()->format('YmdHis').str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                    'user_id' => $user->id,
                    'total_amount' => $seckill->seckill_price,
                    'pay_amount' => $seckill->seckill_price,
                    'status' => Order::STATUS_PENDING,
                    'seckill_id' => $seckill->id,
                ]);

                $this->orderRepository->addItem($order, [
                    'goods_id' => $seckill->goods_id,
                    'num' => 1,
                    'price' => $seckill->seckill_price,
                ]);

                return $order;
            });
        } catch (Throwable $e) {
            // 本地事务失败 -> 补偿 Redis（回补库存 + 移除一人一单标记），避免少卖/锁死用户
            $this->rollbackRedisReservation($seckill->id, $user->id);

            // 【面试备注】Redis 一人一单失效（如重启丢失 Set）时，DB 唯一索引仍能拦住重复订单；
            // 这里识别 1062 并返回已有订单，对用户表现为幂等成功。
            if ($e instanceof QueryException && ($e->errorInfo[1] ?? 0) === 1062) {
                $existing = $this->orderRepository->findByUserAndSeckill($user->id, $seckill->id);
                if ($existing) {
                    return $this->formatOrder($existing, $this->remainingStock($seckill));
                }
            }

            throw $e;
        }

        return $this->formatOrder($order->fresh('items'), $this->remainingStock($seckill));
    }

    private function ensureStockInitialized(Seckill $seckill): void
    {
        // SETNX：并发首次请求只有一个能写入，避免把已扣减的库存覆盖回初始值
        Redis::setnx(RedisKey::seckillStock($seckill->id), $seckill->stock);
    }

    private function runStockScript(Seckill $seckill, int $userId): int
    {
        self::$stockScript ??= file_get_contents(base_path('resources/lua/seckill.lua'));

        return (int) Redis::eval(
            self::$stockScript,
            2,
            RedisKey::seckillStock($seckill->id),
            RedisKey::seckillUsers($seckill->id),
            $userId,
            $seckill->end_time->timestamp
        );
    }

    private function rollbackRedisReservation(int $seckillId, int $userId): void
    {
        self::$rollbackScript ??= file_get_contents(base_path('resources/lua/seckill_rollback.lua'));

        Redis::eval(
            self::$rollbackScript,
            2,
            RedisKey::seckillStock($seckillId),
            RedisKey::seckillUsers($seckillId),
            $userId
        );
    }

    private function remainingStock(Seckill $seckill): int
    {
        $value = Redis::get(RedisKey::seckillStock($seckill->id));

        return $value === null ? $seckill->stock : (int) $value;
    }

    private function formatSeckill(Seckill $seckill, int $remaining): array
    {
        return [
            'id' => $seckill->id,
            'goods_id' => $seckill->goods_id,
            'seckill_price' => $seckill->seckill_price,
            'stock' => $seckill->stock,
            'remaining' => $remaining,
            'start_time' => $seckill->start_time->toDateTimeString(),
            'end_time' => $seckill->end_time->toDateTimeString(),
            'status' => $seckill->status,
            'goods' => $seckill->goods ? [
                'id' => $seckill->goods->id,
                'title' => $seckill->goods->title,
                'cover' => $seckill->goods->cover,
                'price' => $seckill->goods->price,
                'stock' => $seckill->goods->stock,
            ] : null,
        ];
    }

    private function formatOrder(Order $order, int $remaining): array
    {
        return [
            'order_no' => $order->order_no,
            'total_amount' => $order->total_amount,
            'pay_amount' => $order->pay_amount,
            'status' => $order->status,
            'status_text' => $order->statusText(),
            'created_at' => $order->created_at?->toDateTimeString(),
            'remaining' => $remaining,
        ];
    }
}
