<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReview extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'rating',
        'content',
        'sentiment',
        'context',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function toGameState(): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customer_id,
            'rating' => $this->rating,
            'content' => $this->content,
            'sentiment' => $this->sentiment,
            'context' => $this->context,
            'createdAt' => $this->created_at->toIso8601String(),
            'companyName' => $this->customer->company_name,
        ];
    }
}
