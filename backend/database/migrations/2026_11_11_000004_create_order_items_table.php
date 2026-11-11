<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('goods_id');
            $table->unsignedInteger('num')->default(1);
            // 下单时的价格快照，避免商品改价后历史订单金额变化
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index('order_id', 'idx_order_items_order');
            $table->index('goods_id', 'idx_order_items_goods');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
