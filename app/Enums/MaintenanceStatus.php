<?php

namespace App\Enums;

enum MaintenanceStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Terjadwal',
            self::InProgress => 'Sedang Dikerjakan',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal / Rusak',
            self::Canceled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'blue',
            self::InProgress => 'purple',
            self::Completed => 'emerald',
            self::Failed => 'rose',
            self::Canceled => 'gray',
        };
    }
}
