<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Laravel 电商 Demo API',
    description: 'Laravel 10 + Redis 秒杀 Demo。统一返回 {code, message, data}，code=0 表示成功。'
)]
#[OA\Server(url: 'http://127.0.0.1:8000', description: '本地开发环境')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    description: '登录接口返回的 token，放在 Authorization: Bearer {token}'
)]
class OpenApiSpec
{
}
