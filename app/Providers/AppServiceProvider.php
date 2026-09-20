<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
{
    RateLimiter::for(
        'api-gateway',
        function (Request $request) {
            $key = $request->user()
                ? 'user:'.$request->user()->id
                : 'ip:'.$request->ip();

            return Limit::perMinute(60)
                ->by($key);
        }
    );

    // Форсира Laravel да използва чистия APP_URL без добавени портове в Codespaces
    if (str_contains(env('APP_URL'), 'github.dev')) {
        \Illuminate\Support\Facades\URL::forceRootUrl(env('APP_URL'));
        \Illuminate\Support\Facades\URL::forceScheme('https');
    }
}
}