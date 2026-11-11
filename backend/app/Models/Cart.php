<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cart extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'goods_id', 'num', 'is_checked'];

    protected $casts = [
        'num' => 'integer',
        'is_checked' => 'integer',
    ];

    public function goods(): BelongsTo
    {
        return $this->belongsTo(Goods::class, 'goods_id');
    }
}
