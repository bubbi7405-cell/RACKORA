<?php

namespace App\Enums;

use Illuminate\Support\Str;
use App\Models\GameConfig;

class RoomType
{
    public const BASEMENT = 'basement';
    public const GARAGE = 'garage';
    public const SMALL_HALL = 'small_hall';
    public const DATA_CENTER = 'data_center';
    public string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function cases(): array
    {
        $configs = self::getAll();
        $cases = [];
        foreach (array_keys($configs) as $key) {
            $cases[] = new self($key);
        }
        return $cases;
    }

    public static function tryFrom(?string $value): ?self
    {
        if (!$value) return null;
        $configs = self::getAll();
        if (isset($configs[$value])) return new self($value);
        return null; // Don't return default here, follow Enum tryFrom standard
    }

    private static function getAll(): array
    {
        static $cache = null;
        if ($cache === null) {
            $cache = GameConfig::get('location_definitions', [
                'basement' => [
                    'label' => 'Basement',
                    'max_racks' => 2,
                    'max_power_kw' => 10,
                    'max_cooling_kw' => 8,
                    'bandwidth_gbps' => 1,
                    'unlock_cost' => 0,
                    'rent_per_hour' => 0,
                    'required_level' => 1,
                    'dust_rate' => 0.45,
                ],
                'garage' => [
                    'label' => 'Garage',
                    'max_racks' => 6,
                    'max_power_kw' => 50,
                    'max_cooling_kw' => 40,
                    'bandwidth_gbps' => 10,
                    'unlock_cost' => 25000,
                    'rent_per_hour' => 5,
                    'required_level' => 5,
                    'dust_rate' => 0.25,
                ],
                'small_hall' => [
                    'label' => 'Small Hall',
                    'max_racks' => 16,
                    'max_power_kw' => 200,
                    'max_cooling_kw' => 180,
                    'bandwidth_gbps' => 100,
                    'unlock_cost' => 150000,
                    'rent_per_hour' => 50,
                    'required_level' => 15,
                    'dust_rate' => 0.08,
                ],
                'data_center' => [
                    'label' => 'Data Center',
                    'max_racks' => 48,
                    'max_power_kw' => 1000,
                    'max_cooling_kw' => 950,
                    'bandwidth_gbps' => 1000,
                    'unlock_cost' => 1000000,
                    'rent_per_hour' => 500,
                    'required_level' => 30,
                    'dust_rate' => 0.015,
                ]
            ]);
        }
        return $cache;
    }

    private function getConfigValue(string $key, mixed $default = null): mixed
    {
        $configs = self::getAll();
        return $configs[$this->value][$key] ?? $default;
    }

    public function label(): string
    {
        return $this->getConfigValue('label', Str::title(str_replace('_', ' ', $this->value)));
    }

    public function maxRacks(): int
    {
        return (int) $this->getConfigValue('max_racks', 2);
    }

    public function maxPowerKw(): int
    {
        return (int) $this->getConfigValue('max_power_kw', 10);
    }

    public function maxCoolingKw(): int
    {
        return (int) $this->getConfigValue('max_cooling_kw', 8);
    }

    public function bandwidthGbps(): int
    {
        return (int) $this->getConfigValue('bandwidth_gbps', 1);
    }

    public function unlockCost(): float
    {
        return (float) $this->getConfigValue('unlock_cost', 0);
    }

    public function rentPerHour(): float
    {
        return (float) $this->getConfigValue('rent_per_hour', 0);
    }

    public function requiredLevel(): int
    {
        return (int) $this->getConfigValue('required_level', 1);
    }

    public function dustRate(): float
    {
        return (float) $this->getConfigValue('dust_rate', 0.45);
    }
}
