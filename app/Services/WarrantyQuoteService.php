<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\User;
use App\Models\WarrantyQuote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WarrantyQuoteService
{
    /**
     * @return array<int, string>
     */
    public function inclusions(): array
    {
        return [
            'Validación mecánica de 160 puntos',
            'Revisión legal y de documentos (factura, REPUVE y gravámenes)',
            'Acompañamiento de un asesor durante todo el trámite',
            'Protección ante vicios ocultos',
        ];
    }

    public function rate(): float
    {
        $configured = data_get(SiteSetting::get('warranty_config', []), 'service_fee_rate');

        if (is_numeric($configured) && (float) $configured > 0) {
            return (float) $configured / 100;
        }

        return max((float) config('warranty.service_fee_rate', 0.025), 0);
    }

    public function validDays(): int
    {
        return max(1, (int) config('warranty.quote_valid_days', 15));
    }

    public function priceMin(): float
    {
        return (float) config('warranty.price_min', 50000);
    }

    public function priceMax(): float
    {
        return (float) config('warranty.price_max', 1500000);
    }

    public function contactEmail(): string
    {
        $configured = data_get(SiteSetting::get('warranty_config', []), 'contact_email');

        return filled($configured)
            ? (string) $configured
            : (string) (config('mail.from.address') ?: 'garantia@wecar.mx');
    }

    /**
     * @return array{service_fee_rate: float, service_fee: float, total: float, valid_days: int}
     */
    public function quote(float $price): array
    {
        $rate = $this->rate();
        $fee = round($price * $rate, 2);

        return [
            'service_fee_rate' => $rate,
            'service_fee' => $fee,
            'total' => round($price + $fee, 2),
            'valid_days' => $this->validDays(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user, Request $request): WarrantyQuote
    {
        $attempts = 0;

        while (true) {
            try {
                return Cache::lock('warranty-quote:folio', 5)->block(5, function () use ($data, $user, $request): WarrantyQuote {
                    return DB::transaction(function () use ($data, $user, $request): WarrantyQuote {
                        $consecutive = (int) WarrantyQuote::query()->max('consecutive') + 1;
                        $quote = $this->quote((float) $data['car_price']);

                        return WarrantyQuote::query()->create([
                            ...$data,
                            'user_id' => $user->getKey(),
                            'consecutive' => $consecutive,
                            'folio' => 'WG-'.str_pad((string) $consecutive, 6, '0', STR_PAD_LEFT),
                            'service_fee_rate' => $quote['service_fee_rate'],
                            'service_fee' => $quote['service_fee'],
                            'total' => $quote['total'],
                            'currency' => 'MXN',
                            'status' => 'issued',
                            'includes' => $this->inclusions(),
                            'metadata' => [
                                'ip' => $request->ip(),
                                'user_agent' => Str::limit((string) $request->userAgent(), 255),
                            ],
                            'valid_until' => now()->addDays($quote['valid_days']),
                        ]);
                    });
                });
            } catch (UniqueConstraintViolationException $exception) {
                if (++$attempts >= 3) {
                    throw $exception;
                }

                usleep(50_000);
            }
        }
    }

    public function pdf(WarrantyQuote $quote): \Barryvdh\DomPDF\PDF
    {
        $logoPath = public_path('images/wecar-logo.png');
        $logo = is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        return Pdf::setOption([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
        ])
            ->loadView('pdf.warranty-quote', [
                'quote' => $quote,
                'logo' => $logo,
                'contactEmail' => $this->contactEmail(),
            ])
            ->setPaper('a4');
    }
}
