<?php

namespace App\Repositories;

use App\Models\Cart;
use Illuminate\Database\Eloquent\Collection;

class CartRepository
{
    public function listWithGoods(int $userId): Collection
    {
        // 购物车量小，直接 join 商品表取展示字段；注意只 select 需要的列，避免 SELECT *
        return Cart::query()
            ->with(['goods' => function ($query) {
                $query->select('id', 'title', 'cover', 'price', 'stock', 'status');
            }])
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();
    }

    public function findOwned(int $id, int $userId): ?Cart
    {
        return Cart::query()->where('user_id', $userId)->find($id);
    }

    public function findByUserAndGoods(int $userId, int $goodsId): ?Cart
    {
        return Cart::query()
            ->where('user_id', $userId)
            ->where('goods_id', $goodsId)
            ->first();
    }

    public function create(array $data): Cart
    {
        return Cart::create($data);
    }

    public function update(Cart $cart, array $data): bool
    {
        return $cart->update($data);
    }

    public function delete(Cart $cart): void
    {
        $cart->delete();
    }

    /**
     * 结算时按 id 批量取当前用户的购物车行。
     * 条件里带 user_id 是防越权：不能拿别人的 cart_id 下单。
     */
    public function findByIdsForUser(int $userId, array $ids): Collection
    {
        return Cart::query()
            ->with(['goods' => function ($query) {
                $query->select('id', 'title', 'cover', 'price', 'stock', 'status');
            }])
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->get();
    }

    public function deleteByIds(int $userId, array $ids): int
    {
        return Cart::query()
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->delete();
    }
}
