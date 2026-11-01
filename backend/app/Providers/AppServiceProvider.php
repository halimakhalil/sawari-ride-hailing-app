<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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
        // Surface N+1 queries and silently discarded attributes during development.
        Model::shouldBeStrict(! $this->app->isProduction());

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            // Riders and drivers live in separate tables, so ids alone are not unique.
            return Limit::perMinute(240)->by($user ? class_basename($user).':'.$user->getAuthIdentifier() : $request->ip());
        });
    }
}
