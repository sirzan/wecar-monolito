<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\MembershipService;
use App\Services\SearchCouponService;
use App\Services\SearchTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembershipController extends Controller
{
    public function __construct(
        private readonly MembershipService $memberships,
        private readonly SearchTokenService $tokens,
        private readonly SearchCouponService $coupons,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $membership = $this->memberships->active($user);

        return Inertia::render('Panel/Membership', [
            'membership' => $membership ? [
                'id' => $membership->getKey(),
                'ends_at' => $membership->ends_at?->toIso8601String(),
                'status' => $membership->status,
            ] : null,
            'membershipPlan' => $this->memberships->plan(),
            'tokenBalance' => $this->tokens->balance($user),
            'tokenPlans' => Plan::query()
                ->where('type', 'tokens')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'hasCoupon' => $this->coupons->hasCoupon($user),
            'transactions' => $user->tokenTransactions()->latest()->limit(20)->get(),
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_key' => ['required', 'string', 'exists:plans,key_name'],
        ]);

        $plan = Plan::query()
            ->where('key_name', $validated['plan_key'])
            ->where('type', 'membership')
            ->where('is_active', true)
            ->firstOrFail();

        $subscription = $this->memberships->begin($request->user());

        abort_unless($subscription->plan_id === $plan->getKey(), 422, 'La membresía no coincide con el plan.');

        return redirect()
            ->route('checkout.show', $subscription)
            ->with('success', 'Completa el pago para activar tu membresía.');
    }

    public function tokens(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_key' => ['required', 'string', 'exists:plans,key_name'],
        ]);

        $plan = Plan::query()
            ->where('key_name', $validated['plan_key'])
            ->where('type', 'tokens')
            ->where('is_active', true)
            ->firstOrFail();

        $subscription = Subscription::query()->create([
            'user_id' => $request->user()->getKey(),
            'kind' => 'tokens',
            'plan_id' => $plan->getKey(),
            'car_id' => null,
            'status' => 'pending',
            'billing_cycle' => 'one_time',
            'amount' => (float) $plan->price,
            'metadata' => [
                'plan_key' => $plan->key_name,
                'kind' => 'tokens',
                'token_amount' => $plan->token_amount,
            ],
        ]);

        return redirect()
            ->route('checkout.show', $subscription)
            ->with('success', 'Completa el pago para recibir tus tokens.');
    }
}
