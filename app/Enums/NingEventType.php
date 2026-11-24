<?php

namespace App\Enums;

enum NingEventType: string
{
    case OutageDetected = 'OUTAGE_DETECTED';
    case DeviceRestored = 'DEVICE_RESTORED';
    case HighLatencyWarning = 'HIGH_LATENCY_WARNING';

    public function severity(): string
    {
        return match ($this) {
            self::OutageDetected => 'critical',
            self::HighLatencyWarning => 'warning',
            self::DeviceRestored => 'resolved',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OutageDetected => 'Gangguan Jaringan (Outage)',
            self::HighLatencyWarning => 'Peringatan Latensi Tinggi',
            self::DeviceRestored => 'Perangkat Kembali Pulih',
        };
    }
}
