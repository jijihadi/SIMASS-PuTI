<?php

namespace App\Enums;

enum InventoryStatus: string
{
    case Active = 'active';
    case InUse = 'in_use';
    case Spare = 'spare';
    case Maintenance = 'maintenance';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif / Tersedia',
            self::InUse => 'Digunakan / Terpasang',
            self::Spare => 'Cadangan / Gudang',
            self::Maintenance => 'Dalam Perawatan',
            self::Retired => 'Nonaktif / Afkir',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'emerald',
            self::InUse => 'blue',
            self::Spare => 'purple',
            self::Maintenance => 'amber',
            self::Retired => 'gray',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900',
            self::InUse => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-400 dark:border-blue-900',
            self::Spare => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-400 dark:border-purple-900',
            self::Maintenance => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-900',
            self::Retired => 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700',
        };
    }
}
