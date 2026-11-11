<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('cover', 255)->default('');
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('stock')->default(0);
            // 1=上架 0=下架。列表查询会带 status 条件，因此单独建普通索引
            $table->tinyInteger('status')->default(1);
            $table->text('desc')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // 【面试备注】商品列表固定 where status=1 order by id desc，
            // 当商品量大时 (status, id) 联合索引可避免回表排序；Demo 用普通索引即可
            $table->index('status', 'idx_goods_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods');
    }
};
