<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const UPDATED_AT = null;

    public const STATUS_PENDING = 0; // 待支付
    public const STATUS_PAID = 1;    // 已支付
    public const STATUS_CLOSED = 2;  // 已关闭

    protected $fillable = [
        'order_no', 'user_id', 'total_amount', 'pay_amount',
        'status', 'pay_time', 'request_id', 'seckill_id',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'pay_amount' => 'float',
        'status' => 'integer',
        'pay_time' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function statusText(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => '待支付',
            self::STATUS_PAID => '已支付',
            self::STATUS_CLOSED => '已关闭',
            default => '未知',
        };
    }
}
