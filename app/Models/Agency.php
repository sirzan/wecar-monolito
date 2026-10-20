<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'owner_id', 'slug', 'name', 'logo', 'description', 'city', 'state', 'address',
    'phone', 'whatsapp', 'hours', 'legal_name', 'rfc', 'plan', 'plan_expires_at',
    'max_cars', 'is_active',
])]
class Agency extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'plan_expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(AgencyMember::class);
    }

    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CarLead::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
