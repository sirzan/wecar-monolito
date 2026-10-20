<?php

namespace App\Services\ExternalSearch;

class ExternalPlatformConfig
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return collect(config('external_search.platforms', []))
            ->filter(fn (array $platform) => (bool) ($platform['enabled'] ?? false))
            ->sortBy('priority')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function active(): array
    {
        $firecrawl = filled(config('services.firecrawl.key'));
        $apify = (bool) config('services.apify.facebook_enabled') && filled(config('services.apify.token'));

        return collect($this->all())
            ->filter(fn (array $platform) => ($platform['provider'] ?? 'firecrawl') === 'apify' ? $apify : $firecrawl)
            ->values()
            ->all();
    }

    public function available(): bool
    {
        if (! config('external_search.enabled', true)) {
            return false;
        }

        return filled(config('services.firecrawl.key'))
            || ((bool) config('services.apify.facebook_enabled') && filled(config('services.apify.token')));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        return collect($this->all())->firstWhere('id', $id);
    }
}
