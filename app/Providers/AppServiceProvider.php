<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('certificate-verification', static function (Request $request): array {
            $ip = (string) $request->ip();
            $publicId = (string) $request->route('public_id');

            return [
                Limit::perMinute(60)->by("certificate-verification:ip:{$ip}"),
                Limit::perMinute(20)->by("certificate-verification:certificate:{$ip}|{$publicId}"),
            ];
        });

        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasRole('admin')) {
                return true;
            }

            return null;
        });
    }
}
