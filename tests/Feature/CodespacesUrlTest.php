<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;

// Proxy Codespaces meneruskan Host sebagai localhost:8000 (terbukti 14 Nov 2026).
function bootBehindRewrittenHost(string $env, string $appUrl): void
{
    app()['env'] = $env;
    config(['app.url' => $appUrl]);
    url()->setRequest(Request::create('http://localhost:8000/login'));
    (new AppServiceProvider(app()))->boot();
}

test('local https APP_URL pins generated URLs to the APP_URL host', function () {
    bootBehindRewrittenHost('local', 'https://demo-8000.app.github.dev');

    expect(route('login'))->toBe('https://demo-8000.app.github.dev/login')
        ->and(asset('build/app.css'))->toBe('https://demo-8000.app.github.dev/build/app.css');
});

test('production keeps the request host', function () {
    bootBehindRewrittenHost('production', 'https://simass.example');

    expect(route('login'))->toBe('https://localhost:8000/login');
});
