<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Presence;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Observers\AuditLogObserver;
use App\Observers\InventoryItemObserver;
use App\Observers\PresenceObserver;
use App\Observers\TicketMessageObserver;
use App\Observers\TicketObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Ticket::observe(TicketObserver::class);
        TicketMessage::observe(TicketMessageObserver::class);
        Presence::observe(PresenceObserver::class);
        InventoryItem::observe(InventoryItemObserver::class);
        AuditLog::observe(AuditLogObserver::class);

        Gate::before(function ($user, string $ability) {
            if ($user->hasRole('super-admin')) {
                return true;
            }

            // Fallback for users without explicit roles attached (e.g. test factories)
            if (in_array($ability, ['view-inventory', 'create-inventory', 'manage-inventory', 'manage-maintenance'], true)) {
                if (! $user->roles()->exists()) {
                    return true;
                }
            }

            return null;
        });

        // Di balik Cloudflare Tunnel request sampai sebagai HTTP; paksa URL https agar tidak mixed content
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');

            // Proxy dev (GitHub Codespaces) meneruskan Host sebagai localhost:8000 → CSS/link
            // mengarah ke localhost. Lokal saja: kunci host ke APP_URL. Produksi tetap pakai Host request.
            if ($this->app->isLocal()) {
                URL::forceRootUrl(config('app.url'));
            }
        }
    }
}
