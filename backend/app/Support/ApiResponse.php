<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * 统一返回结构 {code, message, data}。
 * 业务失败默认 HTTP 200 + 非 0 code，只有认证失败/限流等需要 HTTP 语义的场景才改状态码。
 */
trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'ok'): JsonResponse
    {
        return response()->json([
            'code' => ErrorCode::SUCCESS,
            'message' => $message,
            'data' => $data,
        ]);
    }

    protected function fail(int $code, ?string $message = null, int $httpStatus = 200): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message ?? ErrorCode::message($code),
            'data' => null,
        ], $httpStatus);
    }
}
