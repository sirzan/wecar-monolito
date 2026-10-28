<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'owner_id', 'agency_id', 'seller_id', 'brand_id', 'model_id', 'version_id', 'status_id',
    'slug', 'year', 'km', 'price', 'previous_price', 'city', 'state', 'description', 'image',
    'gallery', 'transmission', 'fuel', 'body', 'color', 'featured', 'accepts_trade',
    'trade_preference', 'vin', 'serial_number', 'plates', 'plan', 'seo_title',
    'seo_description', 'is_published', 'published_at',
])]
class Car extends Model
{
    use HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'featured' => 'boolean',
            'accepts_trade' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'price' => 'decimal:2',
            'previous_price' => 'decimal:2',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(CarBrand::class, 'brand_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'model_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CarVersion::class, 'version_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(CarStatus::class, 'status_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CarLead::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereDoesntHave('status', fn (Builder $query) => $query->whereIn('name', ['pendiente', 'pausado', 'cancelado']));
    }

    public function scopeSeoIndexable(Builder $query): Builder
    {
        return $query->published()
            ->whereNotNull('slug')
            ->whereHas('status', fn (Builder $query) => $query->whereIn('name', ['en_venta', 'promocion', 'nuevo']));
    }

    public function isSeoIndexable(): bool
    {
        return $this->is_published
            && filled($this->slug)
            && in_array($this->status?->name, ['en_venta', 'promocion', 'nuevo'], true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
