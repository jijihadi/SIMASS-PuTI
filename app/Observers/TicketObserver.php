<?php

namespace App\Observers;

use App\Events\TicketCreated;
use App\Events\TicketStatusUpdated;
use App\Models\Ticket;

class TicketObserver
{
    /**
     * Handle the Ticket "created" event.
     */
    public function created(Ticket $ticket): void
    {
        TicketCreated::dispatch($ticket);
    }

    /**
     * Handle the Ticket "updated" event.
     */
    public function updated(Ticket $ticket): void
    {
        if ($ticket->wasChanged(['status', 'scope', 'assigned_to'])) {
            TicketStatusUpdated::dispatch($ticket);
        }
    }
}
