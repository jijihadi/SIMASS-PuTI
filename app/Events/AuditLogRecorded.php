<?php

namespace App\Events;

use App\Enums\RealtimeChannel;
use App\Models\AuditLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

final class AuditLogRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(
        public readonly int $auditLogId,
        public readonly string $actorName,
        public readonly string $action,
        public readonly string $modelLabel,
        public readonly ?string $ipAddress,
        public readonly string $createdAtFormatted,
    ) {}

    public static function fromModel(AuditLog $log): self
    {
        return new self(
            auditLogId: (int) $log->id,
            actorName: (string) ($log->user?->name ?? 'System / Anonymous'),
            action: (string) $log->action,
            modelLabel: class_basename((string) $log->model_type).' #'.$log->model_id,
            ipAddress: $log->ip_address,
            createdAtFormatted: $log->created_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
        );
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(RealtimeChannel::AdminAudit->value)];
    }

    public function broadcastAs(): string
    {
        return 'audit.log.recorded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->auditLogId,
            'actor_name' => $this->actorName,
            'action' => $this->action,
            'model_label' => $this->modelLabel,
            'ip_address' => $this->ipAddress,
            'created_at_formatted' => $this->createdAtFormatted,
        ];
    }
}
