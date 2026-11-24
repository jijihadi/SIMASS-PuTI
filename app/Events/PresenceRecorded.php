<?php

namespace App\Events;

use App\Enums\RealtimeChannel;
use App\Models\Presence;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

final class PresenceRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(
        public readonly int $presenceId,
        public readonly string $action,
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $tanggal,
        public readonly ?string $jamMasuk,
        public readonly ?string $jamPulang,
        public readonly ?string $totalJam,
    ) {}

    public static function fromModel(Presence $presence, string $action): self
    {
        return new self(
            presenceId: (int) $presence->id,
            action: $action,
            userId: (int) $presence->user_id,
            userName: (string) ($presence->user?->name ?? 'Staf'),
            tanggal: (string) $presence->tanggal,
            jamMasuk: $presence->jam_masuk,
            jamPulang: $presence->jam_pulang,
            totalJam: $presence->total_jam,
        );
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(RealtimeChannel::PresenceLive->value)];
    }

    public function broadcastAs(): string
    {
        return 'presence.recorded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->presenceId,
            'action' => $this->action,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'tanggal' => $this->tanggal,
            'jam_masuk' => $this->jamMasuk,
            'jam_pulang' => $this->jamPulang,
            'total_jam' => $this->totalJam,
        ];
    }
}
