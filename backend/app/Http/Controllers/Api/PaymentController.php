<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OrderService $orderService)
    {
    }

    #[OA\Post(
        path: '/api/payments/callback',
        tags: ['支付'],
        summary: '模拟支付回调（幂等）',
        description: '已支付订单重复回调直接返回成功，不会重复扣减/回补库存；状态用条件更新保证只能 0->1。',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['order_no'],
                properties: [new OA\Property(property: 'order_no', type: 'string', example: 'SO20261111120000123456')]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'code=0 处理成功（含重复回调）')]
    )]
    public function callback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_no' => ['required', 'string', 'max:32'],
        ]);

        return $this->success($this->orderService->payByCallback($data['order_no']), '回调处理成功');
    }
}
