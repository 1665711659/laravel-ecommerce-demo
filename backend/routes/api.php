<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\GoodsController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\SeckillController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API 路由
|--------------------------------------------------------------------------
| 统一前缀 /api，返回结构 {code, message, data}
| 登录后接口统一走 auth:sanctum
*/

Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/goods', [GoodsController::class, 'index']);
Route::get('/goods/{id}', [GoodsController::class, 'show'])->whereNumber('id');

Route::get('/seckill', [SeckillController::class, 'index']);
Route::get('/seckill/{id}', [SeckillController::class, 'show'])->whereNumber('id');

// 模拟第三方支付回调（无登录态，Demo 直接按 order_no 处理，幂等）
Route::post('/payments/callback', [PaymentController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Demo 用：演示更新商品后主动删缓存
    Route::put('/goods/{id}', [GoodsController::class, 'update'])->whereNumber('id');

    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::post('/cart/preview', [CartController::class, 'preview']);
    Route::put('/cart/{id}', [CartController::class, 'update'])->whereNumber('id');
    Route::delete('/cart/{id}', [CartController::class, 'destroy'])->whereNumber('id');

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{orderNo}', [OrderController::class, 'show']);
    Route::post('/orders/{orderNo}/pay', [OrderController::class, 'pay']);

    Route::post('/seckill/{id}/buy', [SeckillController::class, 'buy'])->whereNumber('id');
});
