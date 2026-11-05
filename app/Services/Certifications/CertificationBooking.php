<?php

namespace App\Services\Certifications;

use App\Mail\CertificationRequestAdmin;
use App\Mail\CertificationRequestReceived;
use App\Models\CertificationAppointment;
use App\Models\CertificationSlot;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CertificationBooking
{
    public function __construct(
        private readonly CertificationConfig $config,
        private readonly CertificationAvailability $availability,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function book(array $data, ?User $user = null): CertificationAppointment
    {
        $timezone = $this->config->timezone();
        $date = CarbonImmutable::parse((string) $data['scheduled_date'], $timezone)->startOfDay();
        $time = (string) $data['scheduled_time'];
        $pricing = $this->config->pricing();

        if (! $this->availability->isDayOpen($date)) {
            throw ValidationException::withMessages([
                'scheduled_date' => 'La fecha seleccionada no está disponible.',
            ]);
        }

        $appointment = DB::transaction(function () use ($data, $user, $date, $time, $timezone, $pricing) {
            $slot = $this->lockSlot($date, $time);

            if (! $this->slotHasCapacity($date, $time, $slot->capacity)) {
                throw ValidationException::withMessages([
                    'scheduled_time' => 'Ese horario ya no está disponible. Elige otra hora.',
                ]);
            }

            $startsAt = $this->availability->slotStartAt($date, $time)->utc();

            return CertificationAppointment::query()->create([
                'user_id' => $user?->getKey(),
                'folio' => $this->generateFolio($date, $timezone),
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'whatsapp' => $data['whatsapp'],
                'state' => $data['state'],
                'brand' => $data['brand'],
                'model' => $data['model'],
                'year' => (int) $data['year'],
                'scheduled_date' => $date->toDateString(),
                'scheduled_time' => $time,
                'timezone' => $timezone,
                'starts_at' => $startsAt,
                'duration_minutes' => $this->config->durationMinutes(),
                'service' => $this->config->serviceName(),
                'base_price' => $pricing['base_price'],
                'tax_rate' => $pricing['tax_rate'],
                'tax_amount' => $pricing['tax_amount'],
                'total' => $pricing['total'],
                'currency' => $pricing['currency'],
                'notes' => $data['notes'] ?? null,
                'status' => CertificationAppointment::STATUS_PENDING,
                'status_changed_at' => now(),
            ]);
        });

        $this->sendNotifications($appointment);

        return $appointment;
    }

    public function reschedule(CertificationAppointment $appointment, string $dateString, string $time): CertificationAppointment
    {
        $timezone = $this->config->timezone();
        $date = CarbonImmutable::parse($dateString, $timezone)->startOfDay();

        if (! in_array($appointment->status, CertificationAppointment::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden reprogramar citas pendientes o confirmadas.',
            ]);
        }

        if (! $this->availability->isDayOpen($date)) {
            throw ValidationException::withMessages([
                'scheduled_date' => 'La fecha seleccionada no está disponible.',
            ]);
        }

        DB::transaction(function () use ($appointment, $date, $time, $timezone) {
            $slot = $this->lockSlot($date, $time);

            if (! $this->slotHasCapacity($date, $time, $slot->capacity, $appointment->getKey())) {
                throw ValidationException::withMessages([
                    'scheduled_time' => 'Ese horario ya no está disponible. Elige otra hora.',
                ]);
            }

            $appointment->update([
                'scheduled_date' => $date->toDateString(),
                'scheduled_time' => $time,
                'timezone' => $timezone,
                'starts_at' => $this->availability->slotStartAt($date, $time)->utc(),
                'status_changed_at' => now(),
            ]);
        });

        return $appointment->refresh();
    }

    private function lockSlot(CarbonImmutable $date, string $time): CertificationSlot
    {
        CertificationSlot::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'scheduled_date' => $date->toDateString(),
            'scheduled_time' => $time,
            'capacity' => $this->config->capacityPerSlot(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return CertificationSlot::query()
            ->whereDate('scheduled_date', $date->toDateString())
            ->where('scheduled_time', $time)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function slotHasCapacity(CarbonImmutable $date, string $time, int $capacity, ?string $excludeId = null): bool
    {
        $query = CertificationAppointment::query()
            ->whereDate('scheduled_date', $date->toDateString())
            ->where('scheduled_time', $time)
            ->whereIn('status', CertificationAppointment::ACTIVE_STATUSES);

        if ($excludeId !== null) {
            $query->whereKeyNot($excludeId);
        }

        $booked = $query->count();

        return $booked < max(1, $capacity);
    }

    private function generateFolio(CarbonImmutable $date, string $timezone): string
    {
        $prefix = 'CERT-'.$date->setTimezone($timezone)->format('Ymd');

        do {
            $folio = $prefix.'-'.Str::upper(Str::random(5));
        } while (CertificationAppointment::query()->where('folio', $folio)->exists());

        return $folio;
    }

    private function sendNotifications(CertificationAppointment $appointment): void
    {
        Mail::to($appointment->email)->queue(new CertificationRequestReceived($appointment));
        Mail::to($this->config->adminEmail())->queue(new CertificationRequestAdmin($appointment));

        if ($appointment->user_id) {
            UserNotification::query()->create([
                'user_id' => $appointment->user_id,
                'title' => 'Solicitud de revisión registrada',
                'message' => "Tu cita {$appointment->folio} quedó pendiente de confirmación para el {$appointment->scheduled_date->format('d/m/Y')} a las {$appointment->scheduled_time}.",
                'link' => route('certificaciones'),
                'type' => 'success',
            ]);
        }
    }
}
