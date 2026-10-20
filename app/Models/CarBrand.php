<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'logo', 'sort_order'])]
class CarBrand extends Model
{
    use HasUuids;

    public function models(): HasMany
    {
        return $this->hasMany(CarModel::class, 'brand_id');
    }
}
