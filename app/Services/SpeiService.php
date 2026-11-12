<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Str;

class SpeiService
{
    /**
     * @return array{reference: string, bank_name: string, clabe: string, beneficiary: string, amount: float}
     */
    public function instructions(float $amount): array
    {
        $payments = SiteSetting::get('payments', []);

        abort_unless($this->configured($payments), 422, 'Los datos SPEI aún no están configurados.');

        return [
            'reference' => 'WECAR-'.Str::upper(Str::random(8)),
            'bank_name' => $payments['spei_bank_name'],
            'clabe' => $payments['spei_clabe'],
            'beneficiary' => $payments['spei_beneficiary'],
            'amount' => $amount,
        ];
    }

    public function configured(?array $payments = null): bool
    {
        $payments ??= SiteSetting::get('payments', []);

        return filled($payments['spei_bank_name'] ?? null)
            && filled($payments['spei_beneficiary'] ?? null)
            && preg_match('/^\d{18}$/', (string) ($payments['spei_clabe'] ?? '')) === 1;
    }
}
