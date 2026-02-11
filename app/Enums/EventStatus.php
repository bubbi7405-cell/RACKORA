<?php

namespace App\Enums;

enum EventStatus: string
{
    case WARNING = 'warning';
    case ACTIVE = 'active';
    case ESCALATED = 'escalated';
    case RESOLVED = 'resolved';
    case FAILED = 'failed';

    public function label(): string
    {
        return match($this) {
            self::WARNING => 'Warning',
            self::ACTIVE => 'Active',
            self::ESCALATED => 'Escalated',
            self::RESOLVED => 'Resolved',
            self::FAILED => 'Failed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::WARNING => '#f59e0b',
            self::ACTIVE => '#ef4444',
            self::ESCALATED => '#dc2626',
            self::RESOLVED => '#22c55e',
            self::FAILED => '#991b1b',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::WARNING, self::ACTIVE, self::ESCALATED]);
    }

    public function isResolved(): bool
    {
        return in_array($this, [self::RESOLVED, self::FAILED]);
    }
}
