<?php

namespace App\Services;

use App\Models\SearchAdView;
use App\Models\SearchTokenTransaction;
use App\Models\SearchTokenWallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SearchTokenService
{
    public function balance(User $user): int
    {
        return (int) (SearchTokenWallet::query()->where('user_id', $user->getKey())->value('balance') ?? 0);
    }

    public function credit(User $user, int $amount, string $kind, ?string $reference = null): int
    {
        if ($amount <= 0) {
            return $this->balance($user);
        }

        return DB::transaction(function () use ($user, $amount, $kind, $reference): int {
            if ($reference) {
                $existing = SearchTokenTransaction::query()->where('reference', $reference)->first();

                if ($existing) {
                    return $this->balance($user);
                }
            }

            $wallet = SearchTokenWallet::query()->lockForUpdate()->firstOrCreate(
                ['user_id' => $user->getKey()],
                ['balance' => 0],
            );

            $wallet->increment('balance', $amount);

            SearchTokenTransaction::query()->create([
                'user_id' => $user->getKey(),
                'amount' => $amount,
                'kind' => $kind,
                'reference' => $reference,
            ]);

            return (int) $wallet->fresh()->balance;
        });
    }

    public function consume(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $wallet = SearchTokenWallet::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $wallet || (int) $wallet->balance <= 0) {
                return false;
            }

            $wallet->decrement('balance');

            SearchTokenTransaction::query()->create([
                'user_id' => $user->getKey(),
                'amount' => -1,
                'kind' => 'search',
            ]);

            return true;
        });
    }

    public function startReward(User $user): SearchAdView
    {
        $recent = SearchAdView::query()
            ->where('user_id', $user->getKey())
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDay())
            ->exists();

        abort_if($recent, 422, 'Ya recibiste el token gratuito de hoy.');

        return SearchAdView::query()->create([
            'user_id' => $user->getKey(),
            'started_at' => now(),
        ]);
    }

    public function claimReward(User $user, string $viewId): int
    {
        return DB::transaction(function () use ($user, $viewId): int {
            $view = SearchAdView::query()
                ->whereKey($viewId)
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            abort_if(! $view || $view->completed_at !== null, 422, 'La recompensa no está disponible.');
            abort_if($view->started_at->gt(now()->subSeconds(10)), 422, 'Espera unos segundos para recibir tu token.');

            $recent = SearchAdView::query()
                ->where('user_id', $user->getKey())
                ->whereKeyNot($view->getKey())
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', now()->subDay())
                ->exists();

            abort_if($recent, 422, 'Ya recibiste el token gratuito de hoy.');

            $view->update(['completed_at' => now()]);

            return $this->credit($user, 1, 'ad_reward', 'ad:'.$view->getKey());
        });
    }
}
