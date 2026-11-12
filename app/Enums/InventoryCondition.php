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

    public function badgeClass(): string
    {
        return match ($this) {
            self::Good => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900',
            self::Maintenance => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-900',
            self::Damaged => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-900',
            self::Retired => 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700',
        };
    }
}
