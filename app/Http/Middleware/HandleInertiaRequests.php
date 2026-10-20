<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use App\Support\AgencyContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $agencyContext = app(AgencyContext::class);
        $currentAgency = $user ? $agencyContext->current($request) : null;
        $agencies = $user ? $agencyContext->allFor($request) : collect();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'url' => config('app.url'),
                'locale' => app()->getLocale(),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url ?? $user->profile?->avatar_url,
                    'didit_status' => $user->profile?->didit_status,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'two_factor_enabled' => ! is_null($user->two_factor_confirmed_at),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
            ],
            'currentAgency' => $currentAgency ? [
                'id' => $currentAgency->getKey(),
                'slug' => $currentAgency->slug,
                'name' => $currentAgency->name,
                'logo' => $currentAgency->logo,
                'plan' => $currentAgency->plan,
                'max_cars' => $currentAgency->max_cars,
                'role' => $user?->agencyRole($currentAgency),
            ] : null,
            'agencies' => $agencies->map(fn ($agency) => [
                'id' => $agency->getKey(),
                'slug' => $agency->slug,
                'name' => $agency->name,
            ])->values(),
            'site' => [
                'contact' => SiteSetting::get('contact_info', []),
                'footer' => SiteSetting::get('footer_config', []),
            ],
            'externalSearch' => [
                'enabled' => (bool) config('external_search.enabled', true)
                    && (filled(config('services.firecrawl.key')) || (bool) config('services.apify.facebook_enabled')),
            ],
            'push' => [
                'publicKey' => config('services.webpush.public_key'),
            ],
            'paymentMethods' => [
                'stripe' => (bool) (data_get(SiteSetting::get('payments'), 'stripe_enabled', false)),
                'paypal' => (bool) (data_get(SiteSetting::get('payments'), 'paypal_enabled', false)),
                'spei' => (bool) (data_get(SiteSetting::get('payments'), 'spei_enabled', false)),
            ],
        ];
    }
}
