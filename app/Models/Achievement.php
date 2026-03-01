<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Achievement extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'name',
        'description',
        'icon',
        'category',
        'requirements',
        'points',
        'is_secret',
    ];

    protected $casts = [
        'requirements' => 'array',
        'is_secret' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }
}
