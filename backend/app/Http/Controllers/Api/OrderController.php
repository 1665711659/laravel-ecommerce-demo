<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OrderService $orderService)
    {
    }

    #[OA\Post(
        path: '/api/orders',
        tags: ['订单'],
        summary: '购物车下单（幂等）',
        description: '必须携带 X-Request-Id（或 body.request_id），同一个 token 重复提交只会产生一个订单',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'X-Request-Id', in: 'header', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [new OA\Property(property: 'cart_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2])]
            )
        ),
        responses: [new OA\Response(response: 200, description: '订单详情')]
    )]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cart_ids' => ['sometimes', 'array'],
            'cart_ids.*' => ['integer', 'min:1'],
            'request_id' => ['sometimes', 'string', 'max:64'],
        ]);

        $requestId = $request->header('X-Request-Id') ?: ($data['request_id'] ?? null);

        return $this->success(
            $this->orderService->createFromCart(
                $request->user(),
                $data['cart_ids'] ?? [],
                $requestId
            ),
            '下单成功'
        );
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success($this->orderService->list($request->user()));
    }

    public function show(Request $request, string $orderNo): JsonResponse
    {
        return $this->success($this->orderService->detail($request->user(), $orderNo));
    }

    #[OA\Post(
        path: '/api/orders/{orderNo}/pay',
        tags: ['订单'],
        summary: '模拟支付（幂等：已支付直接返回成功）',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'orderNo', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: '订单详情')]
    )]
    public function pay(Request $request, string $orderNo): JsonResponse
    {
        return $this->success($this->orderService->pay($request->user(), $orderNo), '支付成功');
    }
}
