<?php

namespace App\Events;

use App\Enums\RealtimeChannel;
use App\Models\TicketMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TicketMessagePosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public readonly TicketMessage $message) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        // Hard security boundary: internal notes NEVER touch the public ticket channel
        if ($this->message->is_internal) {
            return [new PrivateChannel(RealtimeChannel::ticketInternal($this->message->ticket_id))];
        }

        $channels = [new PrivateChannel(RealtimeChannel::ticketPublic($this->message->ticket_id))];
        $ticket = $this->message->ticket;

        if ($this->message->sender_type === 'staff' && $ticket?->user_id) {
            $channels[] = new PrivateChannel(RealtimeChannel::userPersonal($ticket->user_id));
        } elseif ($this->message->sender_type === 'user' && $ticket?->assigned_to) {
            $channels[] = new PrivateChannel(RealtimeChannel::userPersonal($ticket->assigned_to));
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'ticket.message.posted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $ticket = $this->message->ticket;

        return [
            'id' => $this->message->id,
            'ticket_id' => $this->message->ticket_id,
            'ticket_number' => $ticket?->ticket_number,
            'ticket_url' => $ticket ? route('ticket.show', $ticket) : null,
            'sender_type' => $this->message->sender_type,
            'sender_name' => $this->message->user?->name ?? ($this->message->sender_type === 'ai_luna' ? 'Luna AI' : 'Sistem'),
            'message' => $this->message->message,
            'attachment_url' => $this->message->attachment ? asset('storage/'.$this->message->attachment) : null,
            'is_internal' => $this->message->is_internal,
            'created_at_human' => $this->message->created_at?->format('H:i'),
            'created_at_full' => $this->message->created_at?->format('d M Y, H:i'),
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
