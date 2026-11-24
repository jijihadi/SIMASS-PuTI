<?php

namespace App\Events;

use App\Enums\RealtimeChannel;
use App\Models\Ticket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TicketCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public readonly Ticket $ticket) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel(RealtimeChannel::NocTickets->value)];

        if ($this->ticket->assigned_to) {
            $channels[] = new PrivateChannel(RealtimeChannel::userPersonal($this->ticket->assigned_to));
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'ticket.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'ticket_url' => route('ticket.show', $this->ticket),
            'title' => $this->ticket->title,
            'category' => $this->ticket->category,
            'service_item' => $this->ticket->service_item,
            'priority' => $this->ticket->priority,
            'location' => $this->ticket->location,
            'status' => [
                'value' => $this->ticket->status,
                'label' => $this->ticket->user_status_label,
                'color' => $this->ticket->user_status_color,
                'needs_clarification' => $this->ticket->needsClarification(),
                'is_final' => $this->ticket->isFinalStatus(),
            ],
            'source_channel' => $this->ticket->source_channel,
            'reporter_name' => $this->ticket->reporter_name ?: $this->ticket->user?->name,
            'assigned_to' => $this->ticket->assigned_to,
            'assignee_name' => $this->ticket->assignee?->name,
            'created_at' => $this->ticket->created_at?->toIso8601String(),
        ];
    }
}
