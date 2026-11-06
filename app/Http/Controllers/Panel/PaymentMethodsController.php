<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodsController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}

    public function index(Request $request): Response
    {
        $payments = SiteSetting::get('payments', []);
        $stripeEnabled = (bool) ($payments['stripe_enabled'] ?? false) && $this->stripe->enabled();

        return Inertia::render('Panel/PaymentMethods', [
            'cards' => $stripeEnabled ? $this->stripe->cards($request->user()) : [],
            'methods' => [
                'stripe' => $stripeEnabled,
                'paypal' => (bool) ($payments['paypal_enabled'] ?? false),
                'spei' => (bool) ($payments['spei_enabled'] ?? false),
            ],
        ]);
    }

    public function portal(Request $request): RedirectResponse
    {
        $payments = SiteSetting::get('payments', []);

        if (! ($payments['stripe_enabled'] ?? false) || ! $this->stripe->enabled()) {
            return back()->with('error', 'Stripe no está habilitado en este momento.');
        }

        return redirect()->away($this->stripe->billingPortalUrl($request->user(), route('panel.payment-methods')));
    }

    public function destroy(Request $request, string $paymentMethod): RedirectResponse
    {
        $user = $request->user();
        $card = collect($this->stripe->cards($user))->firstWhere('id', $paymentMethod);

        if (! $card) {
            return back()->with('error', 'La tarjeta no pertenece a tu cuenta.');
        }

        $this->stripe->detachCard($paymentMethod);

        return back()->with('success', 'Tarjeta eliminada.');
    }
}
