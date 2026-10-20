<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'search_count', 'view_count', 'usage_date'])]
class ExternalSearchUserUsage extends Model
{
    protected $table = 'external_search_user_usage';

    use HasUuids;

    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'search_count' => 'integer',
            'view_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
