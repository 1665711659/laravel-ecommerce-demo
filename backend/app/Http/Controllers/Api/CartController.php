<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CartService $cartService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success($this->cartService->list($request->user()->id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'goods_id' => ['required', 'integer', 'min:1'],
            'num' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        return $this->success(
            $this->cartService->add($request->user()->id, $data['goods_id'], $data['num']),
            '已加入购物车'
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'num' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'is_checked' => ['sometimes', 'boolean'],
        ]);

        return $this->success($this->cartService->update(
            $request->user()->id,
            $id,
            $data['num'] ?? null,
            array_key_exists('is_checked', $data) ? (bool) $data['is_checked'] : null
        ));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->cartService->delete($request->user()->id, $id);

        return $this->success(null, '已删除');
    }

    /** 结算页确认数据 */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cart_ids' => ['sometimes', 'array'],
            'cart_ids.*' => ['integer', 'min:1'],
        ]);

        return $this->success($this->cartService->checkoutPreview(
            $request->user()->id,
            $data['cart_ids'] ?? []
        ));
    }
}
