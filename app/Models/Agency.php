<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'owner_id', 'slug', 'name', 'logo', 'description', 'city', 'state', 'address',
    'phone', 'whatsapp', 'hours', 'legal_name', 'rfc', 'plan', 'plan_id', 'plan_expires_at',
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

    public function planRelation(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isPlanActive(): bool
    {
        return $this->is_active
            && ($this->plan_expires_at === null || $this->plan_expires_at->isFuture());
    }

    public function activeCarsCount(): int
    {
        return $this->cars()
            ->where('is_published', true)
            ->whereHas('status', fn ($query) => $query->whereIn('name', ['en_venta', 'promocion', 'nuevo']))
            ->count();
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
