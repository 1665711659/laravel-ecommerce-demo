<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoodsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class GoodsController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly GoodsService $goodsService)
    {
    }

    #[OA\Get(
        path: '/api/goods',
        tags: ['商品'],
        summary: '商品列表（含进行中的秒杀标签）',
        responses: [new OA\Response(response: 200, description: '商品数组')]
    )]
    public function index(): JsonResponse
    {
        return $this->success($this->goodsService->list());
    }

    #[OA\Get(
        path: '/api/goods/{id}',
        tags: ['商品'],
        summary: '商品详情（缓存穿透/击穿/雪崩处理示例）',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'goods + seckill')]
    )]
    public function show(int $id): JsonResponse
    {
        return $this->success($this->goodsService->detail($id));
    }

    /**
     * Demo 用商品更新接口：演示「先更新 DB 再删缓存」。
     * 真实项目不会让普通用户改商品，这里省略 RBAC。
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'in:0,1'],
            'desc' => ['sometimes', 'nullable', 'string'],
        ]);

        return $this->success($this->goodsService->update($id, $data), '更新成功');
    }
}
