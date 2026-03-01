<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'os_type',
        'os_version',
        'installed_applications',
        'install_cost_mult',
    ];

    protected $casts = [
        'installed_applications' => 'array',
        'install_cost_mult' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
