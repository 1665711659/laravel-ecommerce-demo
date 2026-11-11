<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository
{
    public function create(array $data): Order
    {
        return Order::create($data);
    }

    public function addItem(Order $order, array $item): void
    {
        $order->items()->create($item);
    }

    public function findByOrderNo(string $orderNo): ?Order
    {
        return Order::query()->where('order_no', $orderNo)->first();
    }

    public function findByRequestId(string $requestId): ?Order
    {
        return Order::query()->where('request_id', $requestId)->first();
    }

    public function findByUserAndSeckill(int $userId, int $seckillId): ?Order
    {
        // 只查“仍占用一人一单名额”的订单（待支付/已支付）；已关闭订单不应阻止用户重新抢购
        return Order::query()
            ->where('user_id', $userId)
            ->where('seckill_id', $seckillId)
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_PAID])
            ->first();
    }

    public function listByUser(int $userId): Collection
    {
        // 走 idx_orders_user_status；详情页/列表需要明细，用 with 预加载避免 N+1
        return Order::query()
            ->with('items.goods:id,title,cover')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();
    }

    public function findForUser(string $orderNo, int $userId): ?Order
    {
        return Order::query()
            ->with('items.goods:id,title,cover')
            ->where('order_no', $orderNo)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * 超时未支付订单：where status=0 and created_at <= ?，走 idx_orders_status_created。
     * 分批取 id 再逐单处理，避免一次性锁太多行。
     */
    public function expiredIds(int $minutes, int $limit = 100): array
    {
        return Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();
    }

    public function findForUpdate(int $id): ?Order
    {
        // 关单/支付时用行锁，保证“判断状态 + 改状态”是原子的
        return Order::query()->lockForUpdate()->find($id);
    }

    /**
     * 状态条件更新（单向流转的数据库兜底）。
     * UPDATE orders SET status=1, pay_time=? WHERE id=? AND status=0
     * 返回 0 表示状态已被其他请求改过，调用方据此做幂等处理。
     */
    public function updateStatus(int $id, int $fromStatus, int $toStatus, ?string $payTime = null): int
    {
        $data = ['status' => $toStatus];
        if ($payTime !== null) {
            $data['pay_time'] = $payTime;
        }

        return Order::query()
            ->whereKey($id)
            ->where('status', $fromStatus)
            ->update($data);
    }
}
