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

    /**
     * @return array<int, string>
     */
    public function benefits(): array
    {
        if (is_array($this->features) && $this->features !== []) {
            return array_values($this->features);
        }

        return array_values(array_filter([
            $this->max_cars > 0 ? 'Hasta '.$this->max_cars.' '.($this->max_cars === 1 ? 'auto publicado' : 'autos publicados') : null,
            'Fotos y ficha técnica completa',
            $this->highlight ? 'Posición destacada en el catálogo' : null,
            $this->duration ? $this->duration.' de exposición' : null,
        ]));
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
