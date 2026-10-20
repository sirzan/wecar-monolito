<?php

namespace App\Services\ExternalSearch;

use App\Models\ExternalSearchUsage;
use App\Models\ExternalSearchUserUsage;
use App\Models\User;
use Illuminate\Support\Carbon;

class ExternalUsageService
{
    /**
     * @return array{type: 'user'|'ip', key: string}
     */
    public function identity(?User $user, ?string $ip): array
    {
        return $user
            ? ['type' => 'user', 'key' => $user->getKey()]
            : ['type' => 'ip', 'key' => $ip ?: '127.0.0.1'];
    }

    public function today(): string
    {
        return Carbon::now((string) config('external_search.timezone', 'America/Monterrey'))->toDateString();
    }

    public function limit(string $kind): int
    {
        return $kind === 'search'
            ? (int) config('external_search.free_searches', 3)
            : (int) config('external_search.free_views', 15);
    }

    /**
     * @param  array{type: 'user'|'ip', key: string}  $identity
     * @return array{search: int, view: int}
     */
    public function counts(array $identity): array
    {
        $row = $this->row($identity);

        if (! $row || $row->usage_date?->toDateString() !== $this->today()) {
            return ['search' => 0, 'view' => 0];
        }

        return [
            'search' => (int) $row->search_count,
            'view' => (int) $row->view_count,
        ];
    }

    /**
     * @param  array{type: 'user'|'ip', key: string}  $identity
     * @return array{allowed: bool, count: int, search: int, view: int}
     */
    public function increment(array $identity, string $kind): array
    {
        $row = $this->row($identity);
        $today = $this->today();

        if (! $row) {
            $row = $this->createRow($identity, $today);
        } elseif ($row->usage_date?->toDateString() !== $today) {
            $row->forceFill([
                'search_count' => 0,
                'view_count' => 0,
                'usage_date' => $today,
            ])->save();
        }

        $column = $kind === 'search' ? 'search_count' : 'view_count';
        $current = (int) $row->{$column};
        $limit = $this->limit($kind);

        if ($current >= $limit) {
            return [
                'allowed' => false,
                'count' => $current,
                'search' => (int) $row->search_count,
                'view' => (int) $row->view_count,
            ];
        }

        $row->{$column} = $current + 1;
        $row->save();

        return [
            'allowed' => true,
            'count' => $current + 1,
            'search' => (int) $row->search_count,
            'view' => (int) $row->view_count,
        ];
    }

    /**
     * @param  array{type: 'user'|'ip', key: string}  $identity
     */
    private function row(array $identity): ExternalSearchUsage|ExternalSearchUserUsage|null
    {
        return $identity['type'] === 'user'
            ? ExternalSearchUserUsage::query()->where('user_id', $identity['key'])->first()
            : ExternalSearchUsage::query()->where('ip_address', $identity['key'])->first();
    }

    /**
     * @param  array{type: 'user'|'ip', key: string}  $identity
     */
    private function createRow(array $identity, string $today): ExternalSearchUsage|ExternalSearchUserUsage
    {
        return $identity['type'] === 'user'
            ? ExternalSearchUserUsage::query()->create([
                'user_id' => $identity['key'],
                'search_count' => 0,
                'view_count' => 0,
                'usage_date' => $today,
            ])
            : ExternalSearchUsage::query()->create([
                'ip_address' => $identity['key'],
                'search_count' => 0,
                'view_count' => 0,
                'usage_date' => $today,
            ]);
    }
}
