<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'external_id', 'source', 'source_url', 'slug', 'title', 'make', 'model', 'year',
    'price', 'mileage', 'location', 'images', 'raw_data', 'last_seen_at',
])]
class ExternalListing extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'raw_data' => 'array',
            'price' => 'decimal:2',
            'year' => 'integer',
            'mileage' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    public function isStale(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->lt(now()->subDays(7));
    }
}
