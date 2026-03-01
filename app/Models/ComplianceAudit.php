<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceAudit extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'certificate_id',
        'status',
        'progress',
        'checklog',
        'started_at',
        'completes_at',
    ];

    protected $casts = [
        'checklog' => 'array',
        'started_at' => 'datetime',
        'completes_at' => 'datetime',
        'progress' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }
}
