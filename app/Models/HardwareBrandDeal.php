<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HardwareBrandDeal extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;

    protected $fillable = [
        'user_id', 'brand_name', 'discount_percent',
        'signed_at', 'expires_at', 'status'
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'signed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
