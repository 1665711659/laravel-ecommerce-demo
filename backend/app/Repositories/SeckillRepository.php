<?php

namespace App\Repositories;

use App\Models\Seckill;
use Illuminate\Database\Eloquent\Collection;

class SeckillRepository
{
    public function activeList(): Collection
    {
        return Seckill::query()
            ->with('goods:id,title,cover,price,stock,status')
            ->active()
            ->orderBy('end_time')
            ->get();
    }

    public function findWithGoods(int $id): ?Seckill
    {
        return Seckill::query()
            ->with('goods:id,title,cover,price,stock,status')
            ->find($id);
    }

    public function find(int $id): ?Seckill
    {
        return Seckill::find($id);
    }

    /**
     * MySQL 兜底扣库存（乐观锁条件更新）：
     * UPDATE seckill SET stock = stock - 1 WHERE id = ? AND status = 1 AND stock >= 1
     *
     * 【面试备注】Redis 预扣后为什么还要扣 DB？
     * Redis 抗住绝大部分流量，但 Redis 可能宕机/数据丢失，DB 是最终库存真相；
     * 条件更新 + 受影响行数判断，保证 DB 侧也绝不超卖。
     */
    public function decrementStock(int $id): int
    {
        return Seckill::query()
            ->whereKey($id)
            ->where('status', Seckill::STATUS_ON)
            ->where('stock', '>=', 1)
            ->decrement('stock', 1);
    }

    public function incrementStock(int $id): int
    {
        return Seckill::query()->whereKey($id)->increment('stock', 1);
    }
}
