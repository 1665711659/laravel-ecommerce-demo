<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Repositories\UserRepository;
use App\Support\ErrorCode;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    /**
     * 手机号 + 密码登录，返回 Sanctum token。
     * Demo 不做 RBAC，token 只用于识别用户身份。
     */
    public function login(string $phone, string $password): array
    {
        $user = $this->userRepository->findByPhone($phone);

        if (! $user || ! Hash::check($password, $user->password)) {
            // 统一提示，避免暴露“手机号是否存在”
            throw new BusinessException(ErrorCode::PARAM_ERROR, '手机号或密码错误');
        }

        return [
            'token' => $user->createToken('web')->plainTextToken,
            'user' => [
                'id' => $user->id,
                'nickname' => $user->nickname,
                'phone' => $user->phone,
            ],
        ];
    }

    public function profile($user): array
    {
        return [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'phone' => $user->phone,
        ];
    }
}
