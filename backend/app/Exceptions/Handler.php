<?php

namespace App\Exceptions;

use App\Support\ErrorCode;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        // 业务异常：Service 抛，这里统一转 code/message/data
        $this->renderable(function (BusinessException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code' => $e->getBizCode(),
                    'message' => $e->getMessage(),
                    'data' => null,
                ], $e->getHttpStatus());
            }
        });

        $this->renderable(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code' => ErrorCode::PARAM_ERROR,
                    'message' => $e->validator->errors()->first(),
                    'data' => ['errors' => $e->errors()],
                ], 422);
            }
        });

        $this->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code' => ErrorCode::UNAUTHORIZED,
                    'message' => ErrorCode::message(ErrorCode::UNAUTHORIZED),
                    'data' => null,
                ], 401);
            }
        });

        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code' => ErrorCode::NOT_FOUND,
                    'message' => ErrorCode::message(ErrorCode::NOT_FOUND),
                    'data' => null,
                ], 404);
            }
        });

        // 兜底：API 下未预期异常也返回统一结构，避免前端拿到 HTML 错误页
        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*') && ! $e instanceof HttpExceptionInterface) {
                return response()->json([
                    'code' => ErrorCode::SYSTEM_ERROR,
                    'message' => config('app.debug') ? $e->getMessage() : ErrorCode::message(ErrorCode::SYSTEM_ERROR),
                    'data' => null,
                ], 500);
            }
        });
    }
}
