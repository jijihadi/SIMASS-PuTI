<?php

namespace App\Events;

use App\Enums\RealtimeChannel;
use App\Models\Ticket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TicketStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public readonly Ticket $ticket) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel(RealtimeChannel::NocTickets->value),
            new PrivateChannel(RealtimeChannel::ticketPublic($this->ticket->id)),
        ];

        if ($this->ticket->user_id) {
            $channels[] = new PrivateChannel(RealtimeChannel::userPersonal($this->ticket->user_id));
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'ticket.status.updated';
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
            'scope' => $this->ticket->scope,
            'status' => [
                'value' => $this->ticket->status,
                'label' => $this->ticket->user_status_label,
                'color' => $this->ticket->user_status_color,
                'needs_clarification' => $this->ticket->needsClarification(),
                'is_final' => $this->ticket->isFinalStatus(),
            ],
            'assigned_to' => $this->ticket->assigned_to,
            'assignee_name' => $this->ticket->assignee?->name,
            'sla_due_at' => $this->ticket->sla_due_at?->toIso8601String(),
        ];
    }
}
