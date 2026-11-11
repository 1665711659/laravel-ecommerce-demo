<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seckill', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('goods_id');
            $table->decimal('seckill_price', 10, 2)->default(0);
            // 秒杀独立库存，与 goods.stock 解耦
            $table->unsignedInteger('stock')->default(0);
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            // 1=进行中/启用 0=停用
            $table->tinyInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();

            // 【面试备注】活动校验固定 where goods_id=? and status=?，
            // 复合索引 (goods_id, status) 能直接把扫描行数降到个位数
            $table->index(['goods_id', 'status'], 'idx_seckill_goods_status');
            $table->index(['start_time', 'end_time'], 'idx_seckill_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seckill');
    }
};
