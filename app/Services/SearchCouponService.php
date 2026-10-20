<?php

namespace App\Services;

use App\Models\CouponRecipient;
use App\Models\Plan;
use App\Models\User;

class SearchCouponService
{
    public function hasCoupon(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return CouponRecipient::query()
            ->where('email', mb_strtolower($user->email))
            ->where('coupon_code', (string) config('external_search.token_coupon_code', 'TKNS50'))
            ->exists();
    }

    public function validFor(?User $user, ?string $code, Plan $plan): bool
    {
        if (! $plan->isTokens() || ! $user || ! $code) {
            return false;
        }

        return mb_strtoupper(trim($code)) === mb_strtoupper((string) config('external_search.token_coupon_code', 'TKNS50'))
            && $this->hasCoupon($user);
    }

    public function amount(Plan $plan, ?User $user, ?string $code): float
    {
        $price = (float) $plan->price;

        return $this->validFor($user, $code, $plan)
            ? round($price * (1 - (float) config('external_search.token_coupon_discount', 0.5)), 2)
            : $price;
    }
}
