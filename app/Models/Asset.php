<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    /** @use HasFactory<\Database\Factories\AssetFactory> */
    use HasFactory;


    protected $fillable = [
      'symbol',
      'user_id',
      'amount',
      'locked_amount',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
