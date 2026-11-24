<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'consecutive', 'folio', 'user_id', 'full_name', 'email', 'phone',
    'car_brand', 'car_model', 'car_version', 'car_year', 'car_km',
    'car_price', 'service_fee_rate', 'service_fee', 'total', 'currency',
    'status', 'includes', 'metadata', 'valid_until',
])]
class WarrantyQuote extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'car_year' => 'integer',
            'car_km' => 'integer',
            'car_price' => 'decimal:2',
            'service_fee_rate' => 'decimal:4',
            'service_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'includes' => 'array',
            'metadata' => 'array',
            'valid_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
