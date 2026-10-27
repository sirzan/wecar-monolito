<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key_name', 'type', 'audience', 'name', 'price', 'duration', 'duration_weeks', 'max_cars', 'token_amount', 'highlight', 'features', 'sort_order', 'is_active'])]
class Plan extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'highlight' => 'boolean',
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'token_amount' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function agencies(): HasMany
    {
        return $this->hasMany(Agency::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isPublication(): bool
    {
        return $this->type === 'publication';
    }

    public function isMembership(): bool
    {
        return $this->type === 'membership';
    }

    public function isTokens(): bool
    {
        return $this->type === 'tokens';
    }

    public function isForUser(): bool
    {
        return in_array($this->audience, ['user', 'both'], true);
    }

    public function isForAgency(): bool
    {
        return in_array($this->audience, ['agency', 'both'], true);
    }

    public function scopeForUsers($query)
    {
        return $query->whereIn('audience', ['user', 'both']);
    }

    public function scopeForAgencies($query)
    {
        return $query->whereIn('audience', ['agency', 'both']);
    }

    public function getRouteKeyName(): string
    {
        return 'key_name';
    }
}
