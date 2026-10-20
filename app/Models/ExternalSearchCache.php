<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['query_key', 'query_text', 'platforms', 'results', 'platforms_searched'])]
class ExternalSearchCache extends Model
{
    protected $table = 'external_search_cache';

    use HasUuids;

    protected function casts(): array
    {
        return [
            'platforms' => 'array',
            'results' => 'array',
            'platforms_searched' => 'array',
        ];
    }
}
