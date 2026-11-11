<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Cart;
use App\Models\Goods;
use App\Models\Order;
use App\Models\User;
use App\Repositories\CartRepository;
use App\Repositories\GoodsRepository;
use App\Repositories\OrderRepository;
use App\Repositories\SeckillRepository;
use App\Support\ErrorCode;
use App\Support\RedisKey;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class OrderService
{
    private static ?string $rollbackScript = null;

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly CartRepository $cartRepository,
        private readonly GoodsRepository $goodsRepository,
        private readonly SeckillRepository $seckillRepository,
        private readonly CacheService $cacheService,
    ) {
    }

    /**
     * 购物车下单。
     *
     * 幂等设计（双保险）：
     * 1. Redis SETNX：同一个 X-Request-Id 只放行一次，重复请求直接复用已有订单；
     * 2. DB 唯一索引 uk_orders_request_id：Redis 失效/多实例时钟问题时兜底，捕获 1062 后返回已有订单。
     *
     * 库存：逐商品条件更新（stock >= num），任一失败整单回滚，绝不允许负库存。
     */
    public function createFromCart(User $user, array $cartIds, ?string $requestId): array
    {
        $requestId = $requestId ? mb_substr($requestId, 0, 64) : null;
        $idempotentKey = $requestId ? RedisKey::orderIdempotent($user->id, $requestId) : null;

        // 幂等判断必须放在“校验购物车”之前：
        // 第一次下单成功后购物车行已被删除，重复请求不能再报「购物车为空」，而应直接返回同一订单。
        if ($requestId) {
            $existing = $this->orderRepository->findByRequestId($requestId);
            if ($existing && $existing->user_id === $user->id) {
                return $this->formatOrder($existing);
            }

            if (! Redis::set($idempotentKey, '1', 'EX', 600, 'NX')) {
                $existing = $this->orderRepository->findByRequestId($requestId);
                if ($existing && $existing->user_id === $user->id) {
                    return $this->formatOrder($existing);
                }
                throw new BusinessException(ErrorCode::DUPLICATE_REQUEST);
            }
        }

        try {
            $carts = $cartIds
                ? $this->cartRepository->findByIdsForUser($user->id, $cartIds)
                : $this->cartRepository->listWithGoods($user->id)->where('is_checked', 1);

            if ($carts->isEmpty()) {
                throw new BusinessException(ErrorCode::CART_EMPTY);
            }

            $items = [];
            $total = 0.0;

            foreach ($carts as $cart) {
                /** @var Cart $cart */
                /** @var Goods|null $goods */
                $goods = $cart->goods;
                if (! $goods || $goods->status !== Goods::STATUS_ON) {
                    throw new BusinessException(ErrorCode::GOODS_OFF_SALE, '存在已下架商品，请刷新购物车');
                }
                if ($cart->num > $goods->stock) {
                    throw new BusinessException(ErrorCode::STOCK_NOT_ENOUGH, "商品「{$goods->title}」库存不足");
                }

                $total += $goods->price * $cart->num;
                $items[] = [
                    'goods_id' => $goods->id,
                    'num' => $cart->num,
                    'price' => $goods->price,
                ];
            }

            // 统一按 goods_id 排序后再扣库存，降低并发下单时的死锁概率
            usort($items, fn ($a, $b) => $a['goods_id'] <=> $b['goods_id']);
            $total = round($total, 2);

            $order = DB::transaction(function () use ($user, $items, $total, $carts, $requestId) {
                foreach ($items as $item) {
                    $affected = $this->goodsRepository->decrementStock($item['goods_id'], $item['num']);
                    if ($affected === 0) {
                        // 抛异常触发事务回滚，已扣的其它商品库存一起还原
                        throw new BusinessException(ErrorCode::STOCK_NOT_ENOUGH);
                    }
                }

                $order = $this->orderRepository->create([
                    'order_no' => $this->generateOrderNo(),
                    'user_id' => $user->id,
                    'total_amount' => $total,
                    'pay_amount' => $total,
                    'status' => Order::STATUS_PENDING,
                    'request_id' => $requestId,
                ]);

                foreach ($items as $item) {
                    $this->orderRepository->addItem($order, [
                        'goods_id' => $item['goods_id'],
                        'num' => $item['num'],
                        'price' => $item['price'],
                    ]);
                }

                // 订单主表 + 明细 + 清购物车在同一事务，保证一致性
                $this->cartRepository->deleteByIds($user->id, $carts->pluck('id')->all());

                return $order;
            });
        } catch (QueryException $e) {
            if ($idempotentKey) {
                Redis::del($idempotentKey);
            }

            // 唯一索引兜底：极少数 Redis 去重失效时，数据库仍然只允许一条相同 request_id
            if ($requestId && $this->isDuplicateKey($e)) {
                $existing = $this->orderRepository->findByRequestId($requestId);
                if ($existing && $existing->user_id === $user->id) {
                    return $this->formatOrder($existing);
                }
            }

            throw $e;
        } catch (Throwable $e) {
            // 下单失败释放幂等键，允许客户端用同一个 token 重试
            if ($idempotentKey) {
                Redis::del($idempotentKey);
            }
            throw $e;
        }

        // 事务提交后再删缓存，避免缓存回填了未提交的数据
        foreach ($items as $item) {
            $this->cacheService->forget(RedisKey::goodsDetail($item['goods_id']));
        }
        $this->cacheService->forget(RedisKey::goodsList());

        return $this->formatOrder($order->fresh('items'));
    }

    public function list(User $user): array
    {
        return $this->orderRepository->listByUser($user->id)
            ->map(fn (Order $order) => $this->formatOrder($order))
            ->all();
    }

    public function detail(User $user, string $orderNo): array
    {
        $order = $this->orderRepository->findForUser($orderNo, $user->id);
        if (! $order) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '订单不存在', 404);
        }

        return $this->formatOrder($order);
    }

    /**
     * 模拟支付（前端按钮）+ 支付回调共用同一套状态流转逻辑。
     * 幂等：已支付订单再次支付直接返回成功，不重复加库存（库存在下单时已扣）。
     */
    public function pay(User $user, string $orderNo): array
    {
        $order = $this->orderRepository->findForUser($orderNo, $user->id);
        if (! $order) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '订单不存在', 404);
        }

        return $this->markPaid($order->id);
    }

    /** 支付回调没有登录态，只按 order_no 定位订单 */
    public function payByCallback(string $orderNo): array
    {
        $order = $this->orderRepository->findByOrderNo($orderNo);
        if (! $order) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '订单不存在', 404);
        }

        return $this->markPaid($order->id);
    }

    private function markPaid(int $orderId): array
    {
        return DB::transaction(function () use ($orderId) {
            // 行锁 + 条件更新：支付回调和用户点支付并发时只有一个能成功
            $order = $this->orderRepository->findForUpdate($orderId);

            if (! $order) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '订单不存在', 404);
            }

            if ($order->status === Order::STATUS_PAID) {
                return $this->formatOrder($order); // 幂等返回
            }

            if ($order->status !== Order::STATUS_PENDING) {
                throw new BusinessException(ErrorCode::ORDER_STATUS_INVALID, '订单已关闭，无法支付');
            }

            $affected = $this->orderRepository->updateStatus(
                $orderId,
                Order::STATUS_PENDING,
                Order::STATUS_PAID,
                now()->toDateTimeString()
            );

            if ($affected === 0) {
                // 理论上不会走到（行锁保护），保留条件更新作为最终兜底
                throw new BusinessException(ErrorCode::ORDER_STATUS_INVALID);
            }

            return $this->formatOrder($order->fresh('items'));
        });
    }

    /**
     * 定时任务调用：关闭超时未支付订单并回滚库存。
     * 返回 [扫描数, 关闭数]。
     */
    public function closeExpired(int $minutes): array
    {
        $scanned = 0;
        $closed = 0;

        // 分批扫描，避免一次锁住大量行
        do {
            $ids = $this->orderRepository->expiredIds($minutes, 100);
            foreach ($ids as $id) {
                $scanned++;
                if ($this->closeOrder($id)) {
                    $closed++;
                }
            }
        } while (count($ids) === 100);

        return [$scanned, $closed];
    }

    /**
     * 关单：事务内锁订单 + 状态条件更新，库存回补与关单同事务。
     * 状态只能 0 -> 2，重复执行不会重复回补库存。
     */
    public function closeOrder(int $orderId): bool
    {
        return DB::transaction(function () use ($orderId) {
            $order = $this->orderRepository->findForUpdate($orderId);

            if (! $order || $order->status !== Order::STATUS_PENDING) {
                return false;
            }

            $affected = $this->orderRepository->updateStatus(
                $orderId,
                Order::STATUS_PENDING,
                Order::STATUS_CLOSED
            );

            if ($affected === 0) {
                return false;
            }

            if ($order->seckill_id) {
                // 秒杀订单：回补秒杀库存 + Redis 预扣 + 移除一人一单标记
                $this->seckillRepository->incrementStock($order->seckill_id);
                $this->rollbackSeckillRedis($order->seckill_id, $order->user_id);
            } else {
                $order->load('items');
                foreach ($order->items as $item) {
                    $this->goodsRepository->incrementStock($item->goods_id, $item->num);
                    $this->cacheService->forget(RedisKey::goodsDetail($item->goods_id));
                }
                $this->cacheService->forget(RedisKey::goodsList());
            }

            return true;
        });
    }

    private function rollbackSeckillRedis(int $seckillId, int $userId): void
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

    private function generateOrderNo(): string
    {
        return 'SO'.now()->format('YmdHis').str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? 0) === 1062;
    }

    private function formatOrder(Order $order): array
    {
        $order->loadMissing('items.goods:id,title,cover');

        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'total_amount' => $order->total_amount,
            'pay_amount' => $order->pay_amount,
            'status' => $order->status,
            'status_text' => $order->statusText(),
            'pay_time' => $order->pay_time?->toDateTimeString(),
            'created_at' => $order->created_at?->toDateTimeString(),
            'seckill_id' => $order->seckill_id,
            'items' => $order->items->map(fn ($item) => [
                'goods_id' => $item->goods_id,
                'title' => $item->goods?->title,
                'cover' => $item->goods?->cover,
                'num' => $item->num,
                'price' => $item->price,
            ])->all(),
        ];
    }
}
