<?php

namespace App\Exceptions;

use App\Support\ErrorCode;
use Exception;

/**
 * 业务异常：Service 层只管抛出，由 Handler 统一转成 {code, message, data}。
 * 这样 Controller 不需要写 try/catch，也不会把内部异常细节暴露给前端。
 */
class BusinessException extends Exception
{
    public function __construct(
        private readonly int $bizCode,
        ?string $message = null,
        private readonly int $httpStatus = 200
    ) {
        parent::__construct($message ?? ErrorCode::message($bizCode));
    }

    public function getBizCode(): int
    {
        return $this->bizCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
