<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Canceled = 'canceled';
    case Failed = 'failed';
    case Redirected = 'redirected';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Diterima',
            self::InProgress => 'Sedang Dikerjakan',
            self::Finished => 'Selesai',
            self::Canceled => 'Dibatalkan',
            self::Failed => 'Gagal Ditangani',
            self::Redirected => 'Dialihkan ke Pusat',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::InProgress => 'purple',
            self::Finished => 'emerald',
            self::Canceled => 'gray',
            self::Failed => 'rose',
            self::Redirected => 'orange',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Finished, self::Canceled], true);
    }

    public function stopsSla(): bool
    {
        return in_array($this, [self::Finished, self::Canceled, self::Failed], true);
    }

    public function pausesSla(): bool
    {
        return $this === self::Redirected;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::Canceled, self::Redirected],
            self::InProgress => [self::Finished, self::Failed, self::Canceled, self::Redirected],
            self::Redirected => [self::InProgress, self::Finished, self::Failed],
            self::Failed => [self::InProgress],
            self::Finished, self::Canceled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
