<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'car_id', 'financiera_id', 'assigned_to', 'folio', 'full_name', 'email',
    'phone', 'birthdate', 'rfc', 'curp', 'calle', 'colonia', 'cp', 'alcaldia', 'ciudad',
    'pais', 'ocupacion', 'empresa', 'antiguedad', 'ingreso_mensual', 'deuda_mensual',
    'banco', 'tipo_cuenta', 'car_brand', 'car_model', 'car_version', 'car_year',
    'car_price', 'car_km', 'financiera_name', 'score', 'risk_level', 'down_payment',
    'term_months', 'monthly_payment', 'rate', 'cat', 'total_to_pay', 'service_fee', 'inputs', 'offers',
    'status', 'notes', 'submitted_at',
])]
class CreditApplication extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'submitted_at' => 'datetime',
            'inputs' => 'array',
            'offers' => 'array',
            'ingreso_mensual' => 'decimal:2',
            'deuda_mensual' => 'decimal:2',
            'car_price' => 'decimal:2',
            'down_payment' => 'decimal:2',
            'monthly_payment' => 'decimal:2',
            'rate' => 'decimal:2',
            'cat' => 'decimal:2',
            'total_to_pay' => 'decimal:2',
            'service_fee' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function financiera(): BelongsTo
    {
        return $this->belongsTo(Financiera::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CreditDocument::class);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
