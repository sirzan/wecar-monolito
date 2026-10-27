<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PublicationPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request, PublicationPlanService $publicationPlans): Response
    {
        $user = $request->user();

        $plans = Plan::query()
            ->where('is_active', true)
            ->where('type', 'publication')
            ->whereIn('audience', ['user', 'both'])
            ->orderBy('sort_order')
            ->get();

        $subscription = $publicationPlans->activeForUser($user) ?? $publicationPlans->latestForUser($user);
        $plan = $subscription ? $publicationPlans->planFor($subscription) : null;

        $activePlan = $subscription ? [
            'key' => $plan?->key_name,
            'name' => $plan?->name ?? data_get($subscription->metadata, 'plan_name'),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
            'max_cars' => $publicationPlans->maxCars($subscription),
            'used_cars' => $publicationPlans->activeCarsCount($user),
            'subscription_id' => $subscription->getKey(),
            'active' => $subscription->isActive(),
        ] : null;

        return Inertia::render('Panel/Billing', [
            'plans' => $plans,
            'activePlan' => $activePlan,
            'invoices' => Payment::query()
                ->where('user_id', $user->getKey())
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (Payment $payment) => [
                    'id' => $payment->getKey(),
                    'date' => $payment->created_at?->format('d/m/Y'),
                    'amount' => (float) $payment->amount,
                    'status' => $payment->status,
                ]),
        ]);
    }

    public function renew(Request $request, PublicationPlanService $publicationPlans): RedirectResponse
    {
        $subscription = $publicationPlans->activeForUser($request->user()) ?? $publicationPlans->latestForUser($request->user());

        if (! $subscription) {
            return back()->with('error', 'No tienes un plan para renovar.');
        }

        $plan = $publicationPlans->planFor($subscription);

        if (! $plan || ! $plan->is_active) {
            return back()->with('error', 'El plan ya no está disponible.');
        }

        $pending = Subscription::query()->create([
            'user_id' => $request->user()->getKey(),
            'kind' => 'publication',
            'plan_id' => $plan->getKey(),
            'status' => 'pending',
            'billing_cycle' => 'total',
            'amount' => (float) $plan->price,
            'metadata' => array_merge($publicationPlans->snapshot($plan), [
                'renewal_of' => $subscription->getKey(),
            ]),
        ]);

        return redirect()->route('checkout.show', $pending);
    }
}
