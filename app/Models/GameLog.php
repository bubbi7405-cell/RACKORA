<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'category',
        'message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log(User $user, string $message, string $type = 'info', ?string $category = null, array $metadata = []): self
    {
        return self::create([
            'user_id' => $user->id,
            'message' => $message,
            'type' => $type,
            'category' => $category,
            'metadata' => $metadata,
        ]);
    }
}
