<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Goods;
use App\Repositories\GoodsRepository;
use App\Repositories\SeckillRepository;
use App\Support\ErrorCode;
use App\Support\RedisKey;

class GoodsService
{
    public function __construct(
        private readonly GoodsRepository $goodsRepository,
        private readonly SeckillRepository $seckillRepository,
        private readonly CacheService $cacheService,
    ) {
    }

    /**
     * 首页商品列表：带缓存 + 活动标签。
     * TTL 较短（120s），活动状态变化能较快反映；更新商品时主动删缓存。
     */
    public function list(): array
    {
        return $this->cacheService->remember(RedisKey::goodsList(), 120, function () {
            $seckillByGoods = $this->seckillRepository->activeList()->keyBy('goods_id');

            return $this->goodsRepository->allActive()
                ->map(function (Goods $goods) use ($seckillByGoods) {
                    $item = $this->formatGoods($goods);
                    $seckill = $seckillByGoods->get($goods->id);
                    $item['seckill'] = $seckill ? $this->formatSeckillBrief($seckill) : null;

                    return $item;
                })
                ->all();
        });
    }

    /**
     * 商品详情缓存：
     * - 空值缓存防穿透（CacheService 内处理）
     * - SETNX 互斥重建防击穿
     * - TTL 随机偏移防雪崩
     * 返回值结构：['goods' => ..., 'seckill' => ...]
     */
    public function detail(int $id): array
    {
        $goods = $this->cacheService->remember(
            RedisKey::goodsDetail($id),
            600,
            fn () => $this->goodsRepository->findActive($id)?->toArray()
        );

        if ($goods === null) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '商品不存在或已下架', 404);
        }

        $seckill = $this->seckillRepository->activeList()
            ->firstWhere('goods_id', $id);

        return [
            'goods' => $goods,
            'seckill' => $seckill ? $this->formatSeckillBrief($seckill) : null,
        ];
    }

    /**
     * 更新商品（Demo 用作演示缓存一致性，无复杂 RBAC）。
     *
     * 【面试备注】为什么是「先更新数据库，再删除缓存」，而不是先删缓存？
     * - 先删缓存：删完、DB 还没更新时，并发读会把旧值重新写入缓存，
     *   之后若没有过期时间就会长期脏读；
     * - 先更新库再删缓存：最坏情况是删除失败留下旧缓存（可重试/延迟双删兜底），
     *   但不会出现“旧值被主动写回”的窗口，业界更常用。
     * - 更进一步可用 Canal 订阅 binlog 异步删缓存，降低业务耦合。
     */
    public function update(int $id, array $data): array
    {
        $goods = $this->goodsRepository->find($id);
        if (! $goods) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '商品不存在', 404);
        }

        $this->goodsRepository->update($id, $data);

        // 更新库成功后再删缓存
        $this->cacheService->forget(RedisKey::goodsDetail($id));
        $this->cacheService->forget(RedisKey::goodsList());

        return $this->formatGoods($goods->refresh());
    }

    private function formatGoods(Goods $goods): array
    {
        return [
            'id' => $goods->id,
            'title' => $goods->title,
            'cover' => $goods->cover,
            'price' => $goods->price,
            'stock' => $goods->stock,
            'status' => $goods->status,
            'desc' => $goods->desc,
        ];
    }

    private function formatSeckillBrief($seckill): array
    {
        return [
            'id' => $seckill->id,
            'goods_id' => $seckill->goods_id,
            'seckill_price' => $seckill->seckill_price,
            'stock' => $seckill->stock,
            'start_time' => $seckill->start_time->toDateTimeString(),
            'end_time' => $seckill->end_time->toDateTimeString(),
        ];
    }
}
