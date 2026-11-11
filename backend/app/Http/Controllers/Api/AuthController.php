<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $authService)
    {
    }

    #[OA\Post(
        path: '/api/auth/login',
        tags: ['认证'],
        summary: '手机号 + 密码登录',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['phone', 'password'],
                properties: [
                    new OA\Property(property: 'phone', type: 'string', example: '13800138000'),
                    new OA\Property(property: 'password', type: 'string', example: '123456'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'code=0 成功，data.token 为 Bearer Token')]
    )]
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6', 'max:32'],
        ]);

        return $this->success(
            $this->authService->login($data['phone'], $data['password']),
            '登录成功'
        );
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($this->authService->profile($request->user()));
    }
}
