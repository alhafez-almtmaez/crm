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

        RateLimiter::for('certificate-portal', static function (Request $request): array {
            $ip = (string) $request->ip();
            $portalId = (string) $request->route('portal_id');

            return [
                Limit::perMinute(60)->by("certificate-portal:ip:{$ip}"),
                Limit::perMinute(30)->by("certificate-portal:student:{$ip}|{$portalId}"),
            ];
        });

        RateLimiter::for('certificate-portal-pdf', static function (Request $request): array {
            $ip = (string) $request->ip();
            $portalId = (string) $request->route('portal_id');
            $certificateId = (string) $request->route('certificate_public_id');

            return [
                Limit::perMinute(12)->by("certificate-portal-pdf:ip:{$ip}"),
                Limit::perMinute(6)->by("certificate-portal-pdf:certificate:{$ip}|{$portalId}|{$certificateId}"),
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
