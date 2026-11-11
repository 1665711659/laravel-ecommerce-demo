<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('goods_id');
            $table->unsignedInteger('num')->default(1);
            $table->tinyInteger('is_checked')->default(1);
            $table->timestamp('created_at')->useCurrent();

            // 同一用户的同一商品只保留一行，加购时做「存在则累加」
            $table->unique(['user_id', 'goods_id'], 'uk_carts_user_goods');
            $table->index('goods_id', 'idx_carts_goods');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
