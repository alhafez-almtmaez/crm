<?php

namespace App\Http\Middleware;

use App\Services\System\SystemSettingsService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private readonly SystemSettingsService $systemSettings)
    {
    }

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $settings = $this->systemSettings->get();
        app()->setLocale($settings['language']);
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'name' => $settings['brandName'] ?? config('app.name'),
                'tagline' => $settings['brandTagline'] ?? '',
            ],
            'auth' => [
                'user' => $user
                    ? [
                        ...$user->only(['id', 'name', 'email']),
                        'roles' => $user->getRoleNames()->values()->all(),
                        'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                    ]
                    : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'systemSettings' => $settings,
        ];
    }
}
