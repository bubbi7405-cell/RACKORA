<?php

namespace App\Enums;

enum RoomType: string
{
    case BASEMENT = 'basement';
    case GARAGE = 'garage';
    case SMALL_HALL = 'small_hall';
    case DATA_CENTER = 'data_center';

    public function label(): string
    {
        return match($this) {
            self::BASEMENT => 'Basement',
            self::GARAGE => 'Garage',
            self::SMALL_HALL => 'Small Hall',
            self::DATA_CENTER => 'Data Center',
        };
    }

    public function maxRacks(): int
    {
        return match($this) {
            self::BASEMENT => 2,
            self::GARAGE => 6,
            self::SMALL_HALL => 16,
            self::DATA_CENTER => 48,
        };
    }

    public function maxPowerKw(): int
    {
        return match($this) {
            self::BASEMENT => 10,
            self::GARAGE => 50,
            self::SMALL_HALL => 200,
            self::DATA_CENTER => 1000,
        };
    }

    public function maxCoolingKw(): int
    {
        return match($this) {
            self::BASEMENT => 8,
            self::GARAGE => 40,
            self::SMALL_HALL => 180,
            self::DATA_CENTER => 950,
        };
    }

    public function bandwidthGbps(): int
    {
        return match($this) {
            self::BASEMENT => 1,
            self::GARAGE => 10,
            self::SMALL_HALL => 100,
            self::DATA_CENTER => 1000,
        };
    }

    public function unlockCost(): float
    {
        return match($this) {
            self::BASEMENT => 0,
            self::GARAGE => 25000,
            self::SMALL_HALL => 150000,
            self::DATA_CENTER => 1000000,
        };
    }

    public function rentPerHour(): float
    {
        return match($this) {
            self::BASEMENT => 0,
            self::GARAGE => 5,
            self::SMALL_HALL => 50,
            self::DATA_CENTER => 500,
        };
    }

    public function requiredLevel(): int
    {
        return match($this) {
            self::BASEMENT => 1,
            self::GARAGE => 5,
            self::SMALL_HALL => 15,
            self::DATA_CENTER => 30,
        };
    }

    public function dustRate(): float
    {
        return match($this) {
            self::BASEMENT => 0.45,
            self::GARAGE => 0.25,
            self::SMALL_HALL => 0.08,
            self::DATA_CENTER => 0.015,
        };
    }
}
