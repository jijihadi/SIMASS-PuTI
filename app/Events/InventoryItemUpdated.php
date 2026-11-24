<?php

namespace App\Events;

use App\Enums\RealtimeChannel;
use App\Models\InventoryItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

final class InventoryItemUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(
        public readonly int $itemId,
        public readonly string $action,
        public readonly string $assetCode,
        public readonly string $name,
        public readonly string $category,
        public readonly string $condition,
        public readonly string $conditionLabel,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly ?string $locationDetail,
        public readonly ?string $itemUrl,
    ) {}

    public static function fromModel(InventoryItem $item, string $action): self
    {
        return new self(
            itemId: (int) $item->id,
            action: $action,
            assetCode: (string) $item->asset_code,
            name: (string) $item->name,
            category: (string) $item->category,
            condition: (string) $item->condition,
            conditionLabel: (string) $item->condition_label,
            status: (string) $item->status,
            statusLabel: (string) $item->status_label,
            locationDetail: $item->location_detail,
            itemUrl: $action === 'deleted' ? null : route('inventory.show', $item),
        );
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(RealtimeChannel::InventoryLive->value)];
    }

    public function broadcastAs(): string
    {
        return 'inventory.item.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->itemId,
            'action' => $this->action,
            'asset_code' => $this->assetCode,
            'name' => $this->name,
            'category' => $this->category,
            'condition' => $this->condition,
            'condition_label' => $this->conditionLabel,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'location_detail' => $this->locationDetail,
            'item_url' => $this->itemUrl,
        ];
    }
}
