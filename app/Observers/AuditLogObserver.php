<?php

namespace App\Observers;

use App\Events\AuditLogRecorded;
use App\Models\AuditLog;

class AuditLogObserver
{
    public function created(AuditLog $log): void
    {
        event(AuditLogRecorded::fromModel($log));
    }
}
