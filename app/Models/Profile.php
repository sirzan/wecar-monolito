<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'full_name', 'phone', 'city', 'state', 'birthdate', 'rfc', 'curp', 'avatar_url', 'is_agency', 'metadata', 'didit_session_id', 'didit_status', 'didit_verified_at',
    'calle', 'colonia', 'cp', 'alcaldia', 'pais', 'ocupacion', 'empresa', 'antiguedad',
    'antiguedad_anios', 'ingreso_mensual', 'deuda_mensual', 'enganche_disponible',
    'banco', 'tipo_cuenta', 'tipo_trabajo', 'genero'])]
class Profile extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'is_agency' => 'boolean',
            'metadata' => 'array',
            'didit_verified_at' => 'datetime',
            'antiguedad_anios' => 'integer',
            'ingreso_mensual' => 'decimal:2',
            'deuda_mensual' => 'decimal:2',
            'enganche_disponible' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
