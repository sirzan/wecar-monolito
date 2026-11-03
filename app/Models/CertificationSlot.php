<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['scheduled_date', 'scheduled_time', 'capacity'])]
class CertificationSlot extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'capacity' => 'integer',
        ];
    }
}
