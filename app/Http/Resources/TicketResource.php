<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'service_item' => $this->service_item,
            'authority' => $this->authority,
            'scope' => $this->scope,
            'status' => [
                'value' => $this->status,
                'label' => $this->user_status_label,
                'color' => $this->user_status_color,
                'needs_clarification' => $this->needsClarification(),
                'is_final' => $this->isFinalStatus(),
            ],
            'priority' => $this->priority,
            'location' => $this->location,
            'source_channel' => $this->source_channel,
            'reporter' => [
                'id' => $this->user_id,
                'name' => $this->reporter_name ?: $this->user?->name,
                'phone' => $this->reporter_phone ?: $this->user?->phone,
            ],
            'assignee' => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null,
            'inventory_item' => $this->inventoryItem ? [
                'id' => $this->inventoryItem->id,
                'asset_code' => $this->inventoryItem->asset_code,
                'name' => $this->inventoryItem->name,
                'condition' => $this->inventoryItem->condition,
                'condition_label' => $this->inventoryItem->condition_label,
            ] : null,
            'inventory_items' => $this->linkedInventoryItems()->map(static fn ($item): array => [
                'id' => $item->id,
                'asset_code' => $item->asset_code,
                'name' => $item->name,
                'condition' => $item->condition,
                'condition_label' => $item->condition_label,
            ])->values()->all(),
            'cancel_reason' => $this->cancel_reason,
            'transferred_unit' => $this->transferred_unit,
            'status_note' => $this->status_note,
            'central_ticket_ref' => $this->central_ticket_ref,
            'sla_due_at' => $this->sla_due_at?->toIso8601String(),
            'sla_paused_at' => $this->sla_paused_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'messages' => $this->whenLoaded('messages', fn () => $this->messages
                ->where('is_internal', false)
                ->values()
                ->map(fn ($msg) => [
                    'id' => $msg->id,
                    'sender_type' => $msg->sender_type,
                    'sender_name' => $msg->user?->name ?? ($msg->sender_type === 'ai_luna' ? 'Luna AI' : 'Sistem'),
                    'message' => $msg->message,
                    'created_at' => $msg->created_at?->toIso8601String(),
                ])
            ),
        ];
    }
}
