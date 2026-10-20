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

        return [
            'reference' => 'WECAR-'.Str::upper(Str::random(8)),
            'bank_name' => $payments['spei_bank_name'] ?? 'BBVA',
            'clabe' => $payments['spei_clabe'] ?? '012180000000000000',
            'beneficiary' => $payments['spei_beneficiary'] ?? 'Wecar SA de CV',
            'amount' => $amount,
        ];
    }
}
