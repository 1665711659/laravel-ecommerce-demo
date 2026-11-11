<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function findByPhone(string $phone): ?User
    {
        // uk_users_phone 唯一索引，等值查询 type=const
        return User::query()->where('phone', $phone)->first();
    }
}
