<?php

namespace App\Enums;

enum ServerStatus: string
{
    case OFFLINE = 'offline';
    case PROVISIONING = 'provisioning';
    case ONLINE = 'online';
    case DEGRADED = 'degraded';
    case DAMAGED = 'damaged';
    case MAINTENANCE = 'maintenance';
    case LOCKED = 'locked';

    public function label(): string
    {
        return match($this) {
            self::OFFLINE => 'Offline',
            self::PROVISIONING => 'Provisioning',
            self::ONLINE => 'Online',
            self::DEGRADED => 'Degraded',
            self::DAMAGED => 'Damaged',
            self::MAINTENANCE => 'Maintenance',
            self::LOCKED => 'Locked',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::OFFLINE => '#64748b',
            self::PROVISIONING => '#f59e0b',
            self::ONLINE => '#22c55e',
            self::DEGRADED => '#eab308',
            self::DAMAGED => '#ef4444',
            self::MAINTENANCE => '#6366f1',
            self::LOCKED => '#4f46e5',
        };
    }

    public function isOperational(): bool
    {
        return in_array($this, [self::ONLINE, self::DEGRADED]);
    }

    public function canAcceptOrders(): bool
    {
        return $this === self::ONLINE;
    }
}
