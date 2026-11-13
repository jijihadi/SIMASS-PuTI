<?php

namespace App\Providers;

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
