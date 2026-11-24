<?php

namespace App\Observers;

use App\Events\TicketMessagePosted;
use App\Models\TicketMessage;

class TicketMessageObserver
{
    /**
     * Handle the TicketMessage "created" event.
     */
    public function created(TicketMessage $message): void
    {
        TicketMessagePosted::dispatch($message);
    }
}
