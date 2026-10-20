<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $plans = Plan::query()
            ->where('is_active', true)
            ->where('type', 'publication')
            ->orderBy('sort_order')
            ->get();

        $activePlan = Car::query()
            ->where('owner_id', $user->getKey())
            ->whereNotNull('plan')
            ->latest()
            ->value('plan');

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
}
