<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'folio', 'full_name', 'email', 'whatsapp', 'state',
    'brand', 'model', 'year', 'scheduled_date', 'scheduled_time', 'timezone',
    'starts_at', 'duration_minutes', 'service', 'base_price', 'tax_rate',
    'tax_amount', 'total', 'currency', 'notes', 'status', 'status_changed_at',
    'cancelled_at',
])]
class CertificationAppointment extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    /**
     * @var array<int, string>
     */
    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_CONFIRMED];

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pendiente',
        self::STATUS_CONFIRMED => 'Confirmada',
        self::STATUS_COMPLETED => 'Completada',
        self::STATUS_CANCELLED => 'Cancelada',
        self::STATUS_NO_SHOW => 'No asistió',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'starts_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'year' => 'integer',
            'duration_minutes' => 'integer',
            'base_price' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function vehicleLabel(): string
    {
        return trim($this->brand.' '.$this->model.' '.$this->year);
    }
}
