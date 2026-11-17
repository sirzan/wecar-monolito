<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->configureRateLimiters();
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('external-search', function (Request $request): Limit {
            return $this->limitByRole($request, adminPerMinute: 60, userPerMinute: 30, guestPerMinute: 20);
        });

        RateLimiter::for('external-search-read', function (Request $request): Limit {
            return $this->limitByRole($request, adminPerMinute: 240, userPerMinute: 120, guestPerMinute: 120);
        });

        RateLimiter::for('external-search-resolve', function (Request $request): Limit {
            return $this->limitByRole($request, adminPerMinute: 120, userPerMinute: 60, guestPerMinute: 60);
        });

        RateLimiter::for('price-suggestions', function (Request $request): Limit {
            return $this->limitByRole($request, adminPerMinute: 120, userPerMinute: 60, guestPerMinute: 40);
        });

        RateLimiter::for('car-versions', function (Request $request): Limit {
            return $this->limitByRole($request, adminPerMinute: 240, userPerMinute: 120, guestPerMinute: 120);
        });

        RateLimiter::for('ai-search', function (Request $request): Limit {
            return $this->limitByRole($request, adminPerMinute: 60, userPerMinute: 30, guestPerMinute: 20);
        });
    }

    private function limitByRole(
        Request $request,
        int $adminPerMinute,
        int $userPerMinute,
        int $guestPerMinute,
    ): Limit {
        $user = $request->user();

        if ($user instanceof User && $user->hasAdminPrivileges()) {
            return Limit::perMinute($adminPerMinute)->by('user:'.$user->getAuthIdentifier());
        }

        if ($user instanceof User) {
            return Limit::perMinute($userPerMinute)->by('user:'.$user->getAuthIdentifier());
        }

        return Limit::perMinute($guestPerMinute)->by('ip:'.$request->ip());
    }
}
