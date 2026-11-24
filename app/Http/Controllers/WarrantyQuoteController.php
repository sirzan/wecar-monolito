<?php

namespace App\Http\Controllers;

use App\Models\WarrantyQuote;
use App\Services\WarrantyQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WarrantyQuoteController extends Controller
{
    public function __construct(private readonly WarrantyQuoteService $quotes) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'car_brand' => ['required', 'string', 'max:120'],
            'car_model' => ['required', 'string', 'max:120'],
            'car_version' => ['nullable', 'string', 'max:160'],
            'car_year' => ['required', 'integer', 'min:1990', 'max:'.(date('Y') + 1)],
            'car_km' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'car_price' => [
                'required',
                'numeric',
                'min:'.$this->quotes->priceMin(),
                'max:'.$this->quotes->priceMax(),
            ],
        ]);

        $quote = $this->quotes->create($validated, $request->user(), $request);

        return response()->json([
            'id' => $quote->getKey(),
            'folio' => $quote->folio,
            'consecutive' => $quote->consecutive,
            'car_price' => (float) $quote->car_price,
            'service_fee_rate' => (float) $quote->service_fee_rate,
            'service_fee' => (float) $quote->service_fee,
            'total' => (float) $quote->total,
            'currency' => $quote->currency,
            'valid_until' => $quote->valid_until?->toIso8601String(),
            'pdf_url' => route('garantia.cotizaciones.pdf', $quote),
        ], 201);
    }

    public function pdf(Request $request, WarrantyQuote $quote): SymfonyResponse
    {
        $user = $request->user();

        abort_unless(
            $user !== null
            && ($quote->user_id === $user->getKey() || $user->hasAnyRole(['superadmin', 'manager', 'viewer'])),
            403,
        );

        return $this->quotes
            ->pdf($quote)
            ->stream('cotizacion-garantia-'.$quote->folio.'.pdf');
    }
}
