<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goods extends Model
{
    public const UPDATED_AT = null;

    public const STATUS_ON = 1;
    public const STATUS_OFF = 0;

    protected $table = 'goods';

    protected $fillable = ['title', 'cover', 'price', 'stock', 'status', 'desc'];

    protected $casts = [
        'price' => 'float',
        'stock' => 'integer',
        'status' => 'integer',
    ];
}
