<?php

namespace App\Support;

/**
 * 统一错误码：前端只依赖 code 做分支，message 仅用于提示。
 * 约定：0 成功；1xxx 通用/参数；2xxx 交易；3xxx 秒杀；5xxx 系统。
 */
final class ErrorCode
{
    public const SUCCESS = 0;
    public const PARAM_ERROR = 1001;
    public const UNAUTHORIZED = 1002;
    public const NOT_FOUND = 1003;
    public const METHOD_NOT_ALLOWED = 1004;

    public const STOCK_NOT_ENOUGH = 2001;
    public const GOODS_OFF_SALE = 2002;
    public const ORDER_STATUS_INVALID = 2003;
    public const CART_EMPTY = 2004;

    public const SECKILL_NOT_START = 3001;
    public const SECKILL_ENDED = 3002;
    public const SECKILL_SOLD_OUT = 3003;
    public const SECKILL_DUPLICATE = 3004;
    public const RATE_LIMITED = 3005;
    public const DUPLICATE_REQUEST = 3006;

    public const SYSTEM_ERROR = 5000;

    public static function message(int $code): string
    {
        return match ($code) {
            self::SUCCESS => 'ok',
            self::PARAM_ERROR => '参数错误',
            self::UNAUTHORIZED => '请先登录',
            self::NOT_FOUND => '资源不存在',
            self::METHOD_NOT_ALLOWED => '请求方式不支持',
            self::STOCK_NOT_ENOUGH => '库存不足',
            self::GOODS_OFF_SALE => '商品已下架',
            self::ORDER_STATUS_INVALID => '订单状态不允许该操作',
            self::CART_EMPTY => '请先勾选要结算的商品',
            self::SECKILL_NOT_START => '秒杀活动未开始',
            self::SECKILL_ENDED => '秒杀活动已结束',
            self::SECKILL_SOLD_OUT => '已售罄',
            self::SECKILL_DUPLICATE => '每人限购一件，请勿重复抢购',
            self::RATE_LIMITED => '当前抢购人数过多，请稍后再试',
            self::DUPLICATE_REQUEST => '请求处理中，请勿重复提交',
            default => '系统繁忙，请稍后再试',
        };
    }
}
