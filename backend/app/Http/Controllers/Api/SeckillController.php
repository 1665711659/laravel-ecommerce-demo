<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SeckillService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SeckillController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SeckillService $seckillService)
    {
    }

    public function index(): JsonResponse
    {
        return $this->success($this->seckillService->activities());
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->seckillService->detail($id));
    }

    #[OA\Post(
        path: '/api/seckill/{id}/buy',
        tags: ['秒杀'],
        summary: '秒杀抢购',
        description: '链路：令牌桶限流 -> Redis Lua 原子预扣(库存+一人一单) -> MySQL 条件更新兜底 -> 生成待支付订单。售罄/重复购买直接返回，不查数据库。',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'code=0 抢购成功；3003 售罄；3004 重复购买；3005 限流'),
            new OA\Response(response: 429, description: '令牌桶限流'),
        ]
    )]
    public function buy(Request $request, int $id): JsonResponse
    {
        return $this->success($this->seckillService->buy($request->user(), $id), '抢购成功');
    }
}
