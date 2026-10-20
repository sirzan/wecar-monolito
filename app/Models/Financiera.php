<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'slug', 'nombre', 'tipo', 'logo', 'color', 'tasa_min', 'tasa_max', 'cat_min', 'cat_max',
    'enganche_pct', 'plazo_max', 'score_min', 'destacado', 'aplica_para', 'contacto', 'notas',
    'orden', 'activo',
])]
class Financiera extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'destacado' => 'boolean',
            'activo' => 'boolean',
            'tasa_min' => 'decimal:2',
            'tasa_max' => 'decimal:2',
            'cat_min' => 'decimal:2',
            'cat_max' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
