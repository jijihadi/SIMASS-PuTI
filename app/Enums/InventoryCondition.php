<?php

namespace App\Enums;

enum InventoryCondition: string
{
    case Good = 'good';
    case Maintenance = 'maintenance';
    case Damaged = 'damaged';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Baik / Normal',
            self::Maintenance => 'Dalam Perbaikan',
            self::Damaged => 'Rusak / Bermasalah',
            self::Retired => 'Dihapus / Afkir',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Good => 'emerald',
            self::Maintenance => 'amber',
            self::Damaged => 'rose',
            self::Retired => 'gray',
        };
    }
}
