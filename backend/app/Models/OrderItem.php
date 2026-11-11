<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['order_id', 'goods_id', 'num', 'price'];

    protected $casts = [
        'num' => 'integer',
        'price' => 'float',
    ];

    public function goods(): BelongsTo
    {
        return $this->belongsTo(Goods::class, 'goods_id');
    }
}
