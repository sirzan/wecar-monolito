<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['provider', 'query_text', 'item_count', 'payload'])]
class ExternalSearchDebug extends Model
{
    protected $table = 'external_search_debug';

    use HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'item_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
