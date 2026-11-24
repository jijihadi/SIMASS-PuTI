<?php

namespace App\Events;

use App\Enums\RealtimeChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

final class NetworkAlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(
        public readonly string $eventType,
        public readonly string $severity,
        public readonly string $label,
        public readonly string $deviceName,
        public readonly ?string $deviceIp,
        public readonly ?string $location,
        public readonly ?string $assetCode,
        public readonly ?float $latencyMs,
        public readonly string $message,
        public readonly ?int $ticketId,
        public readonly ?string $ticketNumber,
        public readonly ?string $ticketUrl,
        public readonly string $triggeredAt,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(RealtimeChannel::NocTickets->value)];
    }

    public function broadcastAs(): string
    {
        return 'network.alert.triggered';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'event_type' => $this->eventType,
            'severity' => $this->severity,
            'label' => $this->label,
            'device_name' => $this->deviceName,
            'device_ip' => $this->deviceIp,
            'location' => $this->location,
            'asset_code' => $this->assetCode,
            'latency_ms' => $this->latencyMs,
            'message' => $this->message,
            'ticket_id' => $this->ticketId,
            'ticket_number' => $this->ticketNumber,
            'ticket_url' => $this->ticketUrl,
            'triggered_at' => $this->triggeredAt,
        ];
    }
}
