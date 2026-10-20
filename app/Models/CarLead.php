<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'car_id', 'agency_id', 'owner_id', 'seller_id', 'channel', 'contact_name',
    'contact_phone', 'contact_email', 'message', 'status', 'notes',
])]
class CarLead extends Model
{
    use HasUuids;

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
