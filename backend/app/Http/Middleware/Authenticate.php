<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * API 请求不跳登录页：返回 null 让框架抛 AuthenticationException，
     * 由 Exceptions\Handler 统一渲染 401 + {code:1002}。
     * 若这里 route('login') 而项目没有 web 登录路由，会直接抛 RouteNotFoundException 变成 500。
     */
    protected function redirectTo(Request $request): ?string
    {
        return $request->is('api/*') || $request->expectsJson() ? null : route('login');
    }
}
