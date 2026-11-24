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
        if ($ticket->inventory_item_id) {
            $ticket->inventoryItems()->syncWithoutDetaching([$ticket->inventory_item_id]);
        }

        TicketCreated::dispatch($ticket);
    }

    /**
     * Handle the Ticket "updated" event.
     */
    public function updated(Ticket $ticket): void
    {
        if ($ticket->wasChanged('inventory_item_id') && $ticket->inventory_item_id) {
            $ticket->inventoryItems()->syncWithoutDetaching([$ticket->inventory_item_id]);
        }

        if ($ticket->wasChanged(['status', 'scope', 'assigned_to'])) {
            TicketStatusUpdated::dispatch($ticket);
        }
    }
}
