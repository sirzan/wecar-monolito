<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WarrantyQuote;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarrantyQuoteController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['folio', 'email', 'status', 'from', 'to']);

        $quotes = WarrantyQuote::query()
            ->when($filters['folio'] ?? null, fn ($query, string $folio) => $query->whereLike('folio', '%'.trim($folio).'%'))
            ->when($filters['email'] ?? null, fn ($query, string $email) => $query->whereLike('email', '%'.trim($email).'%'))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (WarrantyQuote $quote) => [
                'id' => $quote->getKey(),
                'consecutive' => $quote->consecutive,
                'folio' => $quote->folio,
                'created_at' => $quote->created_at?->toIso8601String(),
                'valid_until' => $quote->valid_until?->toIso8601String(),
                'status' => $quote->status,
                'full_name' => $quote->full_name,
                'email' => $quote->email,
                'phone' => $quote->phone,
                'car' => trim(implode(' ', array_filter([
                    $quote->car_brand,
                    $quote->car_model,
                    $quote->car_version,
                    (string) $quote->car_year,
                ]))),
                'car_year' => $quote->car_year,
                'car_km' => $quote->car_km,
                'car_price' => (float) $quote->car_price,
                'service_fee_rate' => (float) $quote->service_fee_rate,
                'service_fee' => (float) $quote->service_fee,
                'total' => (float) $quote->total,
                'currency' => $quote->currency,
                'pdf_url' => route('garantia.cotizaciones.pdf', $quote),
            ]);

        return Inertia::render('Admin/WarrantyQuotes', [
            'quotes' => $quotes,
            'filters' => $filters,
            'statuses' => ['issued'],
        ]);
    }
}
