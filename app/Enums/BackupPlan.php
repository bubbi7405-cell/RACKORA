<?php

namespace App\Enums;

enum BackupPlan: string
{
    case NONE = 'none';
    case DAILY = 'daily';
    case HOURLY = 'hourly';
    case TAPE = 'tape';

    public function hourlyCost(): float
    {
        return match($this) {
            self::NONE => 0.0,
            self::DAILY => 0.05, // ~$36/mo per server
            self::HOURLY => 0.25, // ~$180/mo per server (High reliability)
            self::TAPE => 0.50, // Physical tape
        };
    }

    public function recoveryChance(): int
    {
        return match($this) {
            self::NONE => 0,
            self::DAILY => 60,
            self::HOURLY => 98,
            self::TAPE => 100, // Magnetic tape is very stable
        };
    }

    public function label(): string
    {
        return match($this) {
            self::NONE => 'Off',
            self::DAILY => 'Daily ($0.05/hr)',
            self::HOURLY => 'Hourly ($0.25/hr)',
            self::TAPE => 'Physical Tape ($0.50/hr)'
        };
    }
}
