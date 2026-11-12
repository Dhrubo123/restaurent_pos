<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        Gate::before(function (User $user, string $ability): ?bool {
            if (! $user->active) {
                return false;
            }

            return $user->hasPermission($ability) ? true : null;
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower($request->string('email')->toString());

            return Limit::perMinute(5)->by(Str::transliterate($email.'|'.$request->ip()));
        });
    }
}
