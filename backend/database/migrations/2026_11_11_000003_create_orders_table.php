<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 32);
            $table->unsignedBigInteger('user_id');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('pay_amount', 10, 2)->default(0);
            // 0=待支付 1=已支付 2=已关闭，状态只允许 0->1 / 0->2
            $table->tinyInteger('status')->default(0);
            $table->timestamp('pay_time')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // 下单幂等键：前端 X-Request-Id 透传，DB 唯一索引作为 Redis 去重失效时的兜底
            $table->string('request_id', 64)->nullable();
            // 秒杀订单标记 + 一人一单数据库唯一约束（普通订单为 NULL，MySQL 唯一索引允许多个 NULL）
            $table->unsignedBigInteger('seckill_id')->nullable();

            // 【面试备注】一人一单要允许“超时关闭后重新抢”，不能直接对 (user_id, seckill_id) 做唯一索引，
            // 否则关闭的订单会永久占用名额。MySQL 8 可以用生成列 + 唯一索引实现“部分唯一”：
            // 只有待支付(0)/已支付(1)的订单才写入 active_seckill_user_id，关闭(2)后自动变 NULL 释放名额。
            $table->unsignedBigInteger('active_seckill_user_id')
                ->nullable()
                ->storedAs('IF(status IN (0, 1) AND seckill_id IS NOT NULL, user_id, NULL)');

            $table->unique('order_no', 'uk_orders_order_no');
            $table->unique('request_id', 'uk_orders_request_id');
            $table->unique(['seckill_id', 'active_seckill_user_id'], 'uk_orders_seckill_active_user');
            // 订单列表/超时扫描：where user_id=? and status=? / where status=? and created_at<?
            $table->index(['user_id', 'status'], 'idx_orders_user_status');
            $table->index(['status', 'created_at'], 'idx_orders_status_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
