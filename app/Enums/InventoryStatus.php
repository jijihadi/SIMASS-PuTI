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
}
