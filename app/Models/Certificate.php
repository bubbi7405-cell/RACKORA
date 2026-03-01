<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Certificate extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'name',
        'description',
        'category',
        'requirements',
        'bonus_reputation',
    ];

    protected $casts = [
        'requirements' => 'array',
        'bonus_reputation' => 'decimal:2',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_certificates')
            ->withPivot('issued_at', 'expires_at')
            ->withTimestamps();
    }
}
