<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['provider', 'status']);

        $payments = Payment::query()
            ->with(['user', 'plan', 'subscription.plan', 'subscription.car.brand', 'subscription.car.model'])
            ->when($filters['provider'] ?? null, fn ($query, string $provider) => $query->where('provider', $provider))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Payments', [
            'payments' => $payments->through(fn (Payment $payment) => [
                'id' => $payment->getKey(),
                'provider' => $payment->provider,
                'reference' => $payment->provider_reference,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'created_at' => $payment->created_at?->toIso8601String(),
                'user' => $payment->user?->name,
                'email' => $payment->user?->email,
                'plan' => $payment->plan?->name ?? $payment->subscription?->plan?->name,
                'kind' => $payment->subscription?->kind ?? $payment->plan?->type,
                'car' => $payment->subscription?->car
                    ? trim(($payment->subscription->car->brand?->name ?? '').' '.($payment->subscription->car->model?->name ?? '').' '.$payment->subscription->car->year)
                    : null,
            ]),
            'filters' => $filters,
            'providers' => ['stripe', 'paypal', 'spei'],
            'statuses' => ['pending', 'paid', 'failed', 'refunded'],
        ]);
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['paid', 'failed', 'refunded'])],
        ]);

        if ($validated['status'] === 'paid') {
            $this->payments->markPaid($payment);
        } else {
            $payment->update(['status' => $validated['status']]);
        }

        return back()->with('success', 'Pago actualizado.');
    }
}
