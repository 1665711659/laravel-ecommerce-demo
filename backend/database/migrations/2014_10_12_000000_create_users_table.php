<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nickname', 50)->default('用户');
            // 手机号即登录账号：唯一索引既保证业务唯一，也避免重复注册脏数据
            $table->string('phone', 20)->unique('uk_users_phone');
            $table->string('password');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
