<?php

namespace App\Enums;

enum RackType: string
{
    case RACK_12U = 'rack_12u';
    case RACK_24U = 'rack_24u';
    case RACK_42U = 'rack_42u';

    public function label(): string
    {
        return match($this) {
            self::RACK_12U => '12U Rack',
            self::RACK_24U => '24U Rack',
            self::RACK_42U => '42U Rack',
        };
    }

    public function totalUnits(): int
    {
        return match($this) {
            self::RACK_12U => 12,
            self::RACK_24U => 24,
            self::RACK_42U => 42,
        };
    }

    public function maxPowerKw(): int
    {
        return match($this) {
            self::RACK_12U => 3,
            self::RACK_24U => 8,
            self::RACK_42U => 20,
        };
    }

    public function purchaseCost(): float
    {
        return match($this) {
            self::RACK_12U => 500,
            self::RACK_24U => 1200,
            self::RACK_42U => 2500,
        };
    }

    public function requiredLevel(): int
    {
        return match($this) {
            self::RACK_12U => 1,
            self::RACK_24U => 3,
            self::RACK_42U => 8,
        };
    }
}
