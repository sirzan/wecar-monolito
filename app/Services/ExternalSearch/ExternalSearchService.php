<?php

namespace App\Services\ExternalSearch;

use App\Models\User;
use App\Services\ExternalSearch\Providers\ApifyFacebookProvider;
use App\Services\ExternalSearch\Providers\FirecrawlProvider;
use App\Services\MembershipService;
use App\Services\SearchCouponService;
use App\Services\SearchTokenService;
use Illuminate\Support\Facades\Cache;

class ExternalSearchService
{
    public function __construct(
        private readonly ExternalPlatformConfig $platforms,
        private readonly ExternalCarNormalizer $normalizer,
        private readonly ExternalSearchCacheService $cache,
        private readonly ExternalUsageService $usage,
        private readonly SearchTokenService $tokens,
        private readonly MembershipService $membership,
        private readonly SearchCouponService $coupons,
        private readonly FirecrawlProvider $firecrawl,
        private readonly ApifyFacebookProvider $apify,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function status(?User $user, ?string $ip): array
    {
        $identity = $this->usage->identity($user, $ip);
        $counts = $this->usage->counts($identity);
        $subscribed = $this->membership->isSubscribed($user);
        $isAdmin = $user?->hasAdminPrivileges() ?? false;

        return [
            'enabled' => $this->platforms->available(),
            'subscribed' => $subscribed,
            'unlimited' => $this->hasUnlimitedAccess($user),
            'is_admin' => $isAdmin,
            'membership_ends_at' => $user ? $this->membership->active($user)?->ends_at?->toIso8601String() : null,
            'token_balance' => $user ? $this->tokens->balance($user) : 0,
            'searches' => [
                'used' => $counts['search'],
                'remaining' => max(0, $this->usage->limit('search') - $counts['search']),
                'limit' => $this->usage->limit('search'),
            ],
            'views' => [
                'used' => $counts['view'],
                'remaining' => max(0, $this->usage->limit('view') - $counts['view']),
                'limit' => $this->usage->limit('view'),
            ],
            'has_coupon' => $this->coupons->hasCoupon($user),
            'platforms' => collect($this->platforms->active())->map(fn (array $platform) => [
                'id' => $platform['id'],
                'name' => $platform['name'],
                'color' => $platform['color'],
                'initials' => $platform['initials'],
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function search(?User $user, ?string $ip, string $query): array
    {
        $activePlatforms = $this->platforms->active();

        if ($activePlatforms === []) {
            return [
                'results' => [],
                'platformsSearched' => [],
                'error' => 'Búsqueda externa no disponible (API Key faltante).',
                'limitReached' => false,
            ];
        }

        $unlimited = $this->hasUnlimitedAccess($user);
        $identity = $this->usage->identity($user, $ip);
        $usageCount = null;
        $tokenUsed = false;

        if (! $unlimited) {
            $usage = $this->usage->increment($identity, 'search');
            $usageCount = $usage['search'];

            if (! $usage['allowed']) {
                if ($user && $this->tokens->consume($user)) {
                    $tokenUsed = true;
                } else {
                    return [
                        'results' => [],
                        'platformsSearched' => [],
                        'error' => 'LIMIT_REACHED',
                        'limitReached' => true,
                        'searchCount' => $usageCount,
                    ];
                }
            }
        }

        $cacheKey = $this->cache->key($query, $activePlatforms);
        $memoryKey = 'external-search:'.$cacheKey;

        $cached = Cache::get($memoryKey);

        if (is_array($cached) && isset($cached['results'])) {
            return $this->searchResponse($cached, $unlimited, $usageCount, $tokenUsed);
        }

        $databaseCache = $this->cache->get($cacheKey);

        if ($databaseCache) {
            $results = $this->normalizeStoredResults($databaseCache['results'] ?? []);

            if ($results !== []) {
                $payload = [
                    'results' => $results,
                    'platformsSearched' => $databaseCache['platforms_searched'] ?? [],
                ];
                Cache::put($memoryKey, $payload, now()->addMinutes((int) config('external_search.memory_ttl_minutes', 30)));

                return $this->searchResponse($payload, $unlimited, $usageCount, $tokenUsed);
            }
        }

        $cars = [];

        foreach ($activePlatforms as $platform) {
            $found = ($platform['provider'] ?? 'firecrawl') === 'apify'
                ? $this->apify->search($query, $platform)
                : $this->firecrawl->search($query, $platform);

            foreach ($found as $car) {
                $cars[$car['id']] = $car;
            }
        }

        $cars = $this->normalizer->sortByPlatform(array_values($this->normalizer->withIds($cars)));
        $payload = [
            'results' => $cars,
            'platformsSearched' => collect($activePlatforms)->pluck('name')->values()->all(),
        ];

        Cache::put($memoryKey, $payload, now()->addMinutes((int) config('external_search.memory_ttl_minutes', 30)));
        $this->cache->put(
            $cacheKey,
            $query,
            $activePlatforms,
            $cars,
            $payload['platformsSearched'],
        );

        return $this->searchResponse($payload, $unlimited, $usageCount, $tokenUsed);
    }

    /**
     * @return array<string, mixed>
     */
    public function cached(?User $user, int $limit = 24): array
    {
        $unlimited = $this->hasUnlimitedAccess($user);
        $cars = $this->cache->recentCars(max(1, min(60, $limit)));

        return [
            'results' => $this->normalizer->toClient($cars, $unlimited),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(?User $user, ?string $ip, string $id): array
    {
        $sourceUrl = $this->cache->findUrlById($id);

        if (! $sourceUrl) {
            return ['allowed' => false, 'remaining' => 0, 'limitReached' => true];
        }

        if (! $this->normalizer->isLinkAlive($sourceUrl)) {
            return ['allowed' => false, 'remaining' => 0, 'dead' => true];
        }

        if ($this->hasUnlimitedAccess($user)) {
            return [
                'allowed' => true,
                'sourceUrl' => $sourceUrl,
                'remaining' => $this->usage->limit('view'),
            ];
        }

        $identity = $this->usage->identity($user, $ip);
        $usage = $this->usage->increment($identity, 'view');

        if ($usage['allowed']) {
            return [
                'allowed' => true,
                'sourceUrl' => $sourceUrl,
                'remaining' => max(0, $this->usage->limit('view') - $usage['view']),
            ];
        }

        if ($user && $this->tokens->consume($user)) {
            return [
                'allowed' => true,
                'sourceUrl' => $sourceUrl,
                'remaining' => 0,
            ];
        }

        return ['allowed' => false, 'remaining' => 0, 'limitReached' => true];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function searchResponse(array $payload, bool $allowSource, ?int $usageCount, bool $tokenUsed): array
    {
        $results = $this->normalizer->toClient($payload['results'] ?? [], $allowSource);

        return [
            'results' => $results,
            'platformsSearched' => $payload['platformsSearched'] ?? [],
            'searchCount' => $usageCount,
            'tokenUsed' => $tokenUsed,
            'limitReached' => false,
        ];
    }

    /**
     * @param  array<int, mixed>  $rawResults
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStoredResults(array $rawResults): array
    {
        $cars = [];

        foreach ($rawResults as $rawCar) {
            if (! is_array($rawCar)) {
                continue;
            }

            $platform = $this->platforms->find((string) ($rawCar['platformId'] ?? ''));

            if (! $platform) {
                continue;
            }

            $car = $this->normalizer->normalize($rawCar, $platform, true);

            if ($car) {
                $cars[$car['id']] = $car;
            }
        }

        return $this->normalizer->sortByPlatform(array_values($cars));
    }

    private function hasUnlimitedAccess(?User $user): bool
    {
        return $user !== null
            && ($user->hasAdminPrivileges() || $this->membership->isSubscribed($user));
    }
}
