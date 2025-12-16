<?php

namespace App\Models;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    protected $casts = [
        'status' => OrderStatus::class,
        'side' => OrderSide::class
    ];

    protected $fillable = [
        'user_id',
        'symbol',
        'side',
        'price',
        'amount',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen(Builder $query)
    {
        return $query->where('status', OrderStatus::OPEN);
    }
}
