<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Cart;
use App\Models\Goods;
use App\Repositories\CartRepository;
use App\Repositories\GoodsRepository;
use App\Support\ErrorCode;

class CartService
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly GoodsRepository $goodsRepository,
    ) {
    }

    public function list(int $userId): array
    {
        $carts = $this->cartRepository->listWithGoods($userId);

        $items = $carts->map(function (Cart $cart) {
            $goods = $cart->goods;

            return [
                'id' => $cart->id,
                'goods_id' => $cart->goods_id,
                'num' => $cart->num,
                'is_checked' => $cart->is_checked,
                'title' => $goods?->title,
                'cover' => $goods?->cover,
                'price' => $goods?->price,
                'stock' => $goods?->stock,
                'status' => $goods?->status,
                'subtotal' => $goods ? round($goods->price * $cart->num, 2) : 0,
            ];
        })->values()->all();

        $checkedTotal = collect($items)
            ->where('is_checked', 1)
            ->sum('subtotal');

        return [
            'items' => $items,
            'checked_total' => round((float) $checkedTotal, 2),
        ];
    }

    public function add(int $userId, int $goodsId, int $num): array
    {
        $goods = $this->goodsRepository->findActive($goodsId);
        if (! $goods) {
            throw new BusinessException(ErrorCode::GOODS_OFF_SALE);
        }

        $cart = $this->cartRepository->findByUserAndGoods($userId, $goodsId);
        $newNum = ($cart?->num ?? 0) + $num;

        if ($newNum > $goods->stock) {
            throw new BusinessException(ErrorCode::STOCK_NOT_ENOUGH);
        }

        if ($cart) {
            // 重复加购做累加，而不是插新行（uk_carts_user_goods 保证唯一）
            $this->cartRepository->update($cart, ['num' => $newNum, 'is_checked' => 1]);
        } else {
            $cart = $this->cartRepository->create([
                'user_id' => $userId,
                'goods_id' => $goodsId,
                'num' => $num,
                'is_checked' => 1,
            ]);
        }

        return ['id' => $cart->id, 'num' => $newNum];
    }

    public function update(int $userId, int $cartId, ?int $num, ?bool $isChecked): array
    {
        $cart = $this->cartRepository->findOwned($cartId, $userId);
        if (! $cart) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '购物车记录不存在', 404);
        }

        $data = [];

        if ($num !== null) {
            $goods = $this->goodsRepository->findActive($cart->goods_id);
            if (! $goods) {
                throw new BusinessException(ErrorCode::GOODS_OFF_SALE);
            }
            if ($num < 1 || $num > $goods->stock) {
                throw new BusinessException(ErrorCode::STOCK_NOT_ENOUGH);
            }
            $data['num'] = $num;
        }

        if ($isChecked !== null) {
            $data['is_checked'] = $isChecked ? 1 : 0;
        }

        if ($data) {
            $this->cartRepository->update($cart, $data);
        }

        return ['id' => $cart->id, 'num' => $cart->num, 'is_checked' => $cart->is_checked];
    }

    public function delete(int $userId, int $cartId): void
    {
        $cart = $this->cartRepository->findOwned($cartId, $userId);
        if (! $cart) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '购物车记录不存在', 404);
        }

        $this->cartRepository->delete($cart);
    }

    /**
     * 结算页数据：只取当前用户、指定 id 的购物车行。
     * 未传 id 时默认取所有勾选行，方便“全选结算”。
     */
    public function checkoutPreview(int $userId, array $cartIds): array
    {
        $carts = $cartIds
            ? $this->cartRepository->findByIdsForUser($userId, $cartIds)
            : $this->cartRepository->listWithGoods($userId)->where('is_checked', 1);

        if ($carts->isEmpty()) {
            throw new BusinessException(ErrorCode::CART_EMPTY);
        }

        $items = [];
        $total = 0.0;

        foreach ($carts as $cart) {
            /** @var Goods|null $goods */
            $goods = $cart->goods;
            if (! $goods || $goods->status !== Goods::STATUS_ON) {
                throw new BusinessException(ErrorCode::GOODS_OFF_SALE, "商品「{$cart->goods_id}」已下架");
            }
            if ($cart->num > $goods->stock) {
                throw new BusinessException(ErrorCode::STOCK_NOT_ENOUGH, "商品「{$goods->title}」库存不足");
            }

            $subtotal = round($goods->price * $cart->num, 2);
            $total += $subtotal;
            $items[] = [
                'cart_id' => $cart->id,
                'goods_id' => $goods->id,
                'title' => $goods->title,
                'cover' => $goods->cover,
                'price' => $goods->price,
                'num' => $cart->num,
                'subtotal' => $subtotal,
            ];
        }

        return [
            'items' => $items,
            'cart_ids' => $carts->pluck('id')->all(),
            'total_amount' => round($total, 2),
        ];
    }
}
