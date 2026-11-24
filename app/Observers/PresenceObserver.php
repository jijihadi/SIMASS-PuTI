<?php

namespace App\Observers;

use App\Events\PresenceRecorded;
use App\Models\Presence;

class PresenceObserver
{
    public function created(Presence $presence): void
    {
        event(PresenceRecorded::fromModel($presence, 'check_in'));
    }

    public function updated(Presence $presence): void
    {
        if (! $presence->wasChanged(['jam_masuk', 'jam_pulang', 'pekerjaan', 'total_jam'])) {
            return;
        }

        $action = $presence->wasChanged('jam_pulang') && $presence->jam_pulang
            ? 'check_out'
            : 'updated';

        event(PresenceRecorded::fromModel($presence, $action));
    }

    public function deleted(Presence $presence): void
    {
        event(PresenceRecorded::fromModel($presence, 'deleted'));
    }
}
