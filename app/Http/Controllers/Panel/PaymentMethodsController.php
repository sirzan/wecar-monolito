<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\PaymentMethodsService;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PaymentMethodsController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly PaymentMethodsService $paymentMethods,
    ) {}

    public function index(Request $request): Response
    {
        $payments = SiteSetting::get('payments', []);
        $payments = is_array($payments) ? $payments : [];
        $frontendMap = $this->paymentMethods->frontendMap($payments);
        $stripeEnabled = $frontendMap['stripe'] ?? false;
        $cards = [];
        $stripeError = null;

        if ($stripeEnabled) {
            try {
                $cards = $this->stripe->cards($request->user());
            } catch (\Throwable $exception) {
                report($exception);
                $stripeError = 'No pudimos consultar tus tarjetas en este momento.';
            }
        }

        return Inertia::render('Panel/PaymentMethods', [
            'cards' => $cards,
            'stripeError' => $stripeError,
            'methods' => $frontendMap,
            'methodOptions' => $this->paymentMethods->options($payments),
        ]);
    }

    public function portal(Request $request): SymfonyResponse|RedirectResponse
    {
        $payments = SiteSetting::get('payments', []);

        if (! ($payments['stripe_enabled'] ?? false) || ! $this->stripe->enabled()) {
            return back()->with('error', 'Stripe no está habilitado en este momento.');
        }

        try {
            return Inertia::location($this->stripe->billingPortalUrl($request->user(), route('panel.payment-methods')));
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'No pudimos abrir el portal de Stripe.');
        }
    }

    public function destroy(Request $request, string $paymentMethod): RedirectResponse
    {
        $user = $request->user();
        $card = collect($this->stripe->cards($user))->firstWhere('id', $paymentMethod);

        if (! $card) {
            return back()->with('error', 'La tarjeta no pertenece a tu cuenta.');
        }

        try {
            $this->stripe->detachCard($paymentMethod);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'No pudimos eliminar la tarjeta.');
        }

        return back()->with('success', 'Tarjeta eliminada.');
    }
}
