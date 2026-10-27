<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['subject_type', 'subject_id', 'reminder_key', 'sent_at'])]
class PlanReminder extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
