<?php

namespace App\Repositories;

use App\Models\Goods;
use Illuminate\Database\Eloquent\Collection;

/**
 * 数据访问层：只做 SQL/Eloquent，不掺业务判断。
 * Controller -> Service -> Repository 分层后，缓存、事务等逻辑都在 Service，仓储可复用可单测。
 */
class GoodsRepository
{
    public function allActive(): Collection
    {
        // 走 idx_goods_status：status=1 后按主键倒序，商品量不大时无需额外排序索引
        return Goods::query()
            ->where('status', Goods::STATUS_ON)
            ->orderByDesc('id')
            ->get();
    }

    public function find(int $id): ?Goods
    {
        return Goods::find($id);
    }

    public function findActive(int $id): ?Goods
    {
        return Goods::query()
            ->where('status', Goods::STATUS_ON)
            ->find($id);
    }

    /**
     * 乐观锁扣库存：条件更新，靠 MySQL 行锁 + WHERE 条件保证不会扣成负数。
     * SQL: UPDATE goods SET stock = stock - ? WHERE id = ? AND status = 1 AND stock >= ?
     * 返回受影响行数：0 表示库存不足/已下架，由 Service 抛业务异常。
     *
     * 【面试备注】与 SELECT ... FOR UPDATE 悲观锁对比：
     * - 条件更新不需要先查再改，SQL 更短、持有锁时间更短，适合扣库存这种单行写；
     * - 悲观锁适合“读后要基于多行数据做复杂判断”的场景，但并发高时容易排队。
     */
    public function decrementStock(int $id, int $num): int
    {
        return Goods::query()
            ->whereKey($id)
            ->where('status', Goods::STATUS_ON)
            ->where('stock', '>=', $num)
            ->decrement('stock', $num);
    }

    /** 关单/支付失败时回补库存 */
    public function incrementStock(int $id, int $num): int
    {
        return Goods::query()->whereKey($id)->increment('stock', $num);
    }

    public function update(int $id, array $data): int
    {
        return Goods::query()->whereKey($id)->update($data);
    }
}
