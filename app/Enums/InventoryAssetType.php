<?php

namespace App\Enums;

enum InventoryAssetType: string
{
    case Physical = 'physical';
    case Digital = 'digital';

    public function label(): string
    {
        return match ($this) {
            self::Physical => 'Fisik',
            self::Digital => 'Digital / Lisensi',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Physical => 'cpu',
            self::Digital => 'cloud',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Physical => 'blue',
            self::Digital => 'purple',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Physical => 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800',
            self::Digital => 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-200 dark:border-purple-800',
        };
    }
}

