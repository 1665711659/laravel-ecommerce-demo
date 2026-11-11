<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seckill extends Model
{
    public const UPDATED_AT = null;

    public const STATUS_ON = 1;
    public const STATUS_OFF = 0;

    protected $table = 'seckill';

    protected $fillable = ['goods_id', 'seckill_price', 'stock', 'start_time', 'end_time', 'status'];

    protected $casts = [
        'seckill_price' => 'float',
        'stock' => 'integer',
        'status' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function goods(): BelongsTo
    {
        return $this->belongsTo(Goods::class, 'goods_id');
    }

    /** 活动进行中：status=1 且当前时间落在 [start_time, end_time] */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('status', self::STATUS_ON)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now);
    }
}
