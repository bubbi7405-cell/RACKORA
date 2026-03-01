<?php

namespace App\Enums;

enum RackType: string
{
    case RACK_12U = 'rack_12u';
    case RACK_24U = 'rack_24u';
    case RACK_42U = 'rack_42u';
    case RACK_42U_HD = 'rack_42u_hd';
    case RACK_40U_MONOLITH = 'rack_40u_monolith'; // FEATURE 85
    case CRYO_RACK = 'cryo_rack';

    public function label(): string
    {
        return match($this) {
            self::RACK_12U => '12U Rack',
            self::RACK_24U => '24U Rack',
            self::RACK_42U => '42U Standard',
            self::RACK_42U_HD => '42U High-Density',
            self::RACK_40U_MONOLITH => '40U Monolith Chassis',
            self::CRYO_RACK => 'Cryo-Vault 24U',
        };
    }

    public function totalUnits(): int
    {
        return match($this) {
            self::RACK_12U => 12,
            self::RACK_24U => 24,
            self::RACK_42U => 42,
            self::RACK_42U_HD => 42,
            self::RACK_40U_MONOLITH => 40,
            self::CRYO_RACK => 24,
        };
    }

    public function maxPowerKw(): int
    {
        return match($this) {
            self::RACK_12U => 3,
            self::RACK_24U => 8,
            self::RACK_42U => 20,
            self::RACK_42U_HD => 32,
            self::RACK_40U_MONOLITH => 48, // Exceptional power for its size
            self::CRYO_RACK => 50,
        };
    }

    public function purchaseCost(): float
    {
        return match($this) {
            self::RACK_12U => 500,
            self::RACK_24U => 1200,
            self::RACK_42U => 2500,
            self::RACK_42U_HD => 6000,
            self::RACK_40U_MONOLITH => 12000, // Premium price
            self::CRYO_RACK => 15000,
        };
    }

    public function requiredLevel(): int
    {
        return match($this) {
            self::RACK_12U => 1,
            self::RACK_24U => 3,
            self::RACK_42U => 8,
            self::RACK_42U_HD => 15,
            self::RACK_40U_MONOLITH => 22, // Lategame
            self::CRYO_RACK => 25,
        };
    }
}
