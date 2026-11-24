<?php

use App\Enums\RealtimeChannel;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(RealtimeChannel::NocTickets->value, function (User $user): bool {
    return (bool) $user->is_active && $user->isTicketingStaff();
});

Broadcast::channel(RealtimeChannel::TicketPublic->value, function (User $user, int|string $ticketId): bool {
    if (! $user->is_active || ! ctype_digit((string) $ticketId)) {
        return false;
    }

    if ($user->isTicketingStaff()) {
        return true;
    }

    return Ticket::whereKey((int) $ticketId)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel(RealtimeChannel::TicketInternal->value, function (User $user, int|string $ticketId): bool {
    return (bool) $user->is_active
        && ctype_digit((string) $ticketId)
        && $user->isTicketingStaff();
});

Broadcast::channel(RealtimeChannel::TicketPresence->value, function (User $user, int|string $ticketId): array|false {
    if (! $user->is_active || ! ctype_digit((string) $ticketId)) {
        return false;
    }

    $isStaff = $user->isTicketingStaff();
    if (! $isStaff) {
        $ownsTicket = Ticket::whereKey((int) $ticketId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $ownsTicket) {
            return false;
        }
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'is_staff' => $isStaff,
    ];
});

Broadcast::channel(RealtimeChannel::UserPersonal->value, function (User $user, int|string $userId): bool {
    return (bool) $user->is_active
        && ctype_digit((string) $userId)
        && $user->id === (int) $userId;
});

Broadcast::channel(RealtimeChannel::PresenceLive->value, function (User $user): bool {
    return (bool) $user->is_active
        && ($user->can('manage-presence') || $user->isTicketingStaff());
});

Broadcast::channel(RealtimeChannel::InventoryLive->value, function (User $user): bool {
    return (bool) $user->is_active && $user->can('view-inventory');
});

Broadcast::channel(RealtimeChannel::AdminAudit->value, function (User $user): bool {
    return (bool) $user->is_active && $user->hasRole('super-admin');
});
