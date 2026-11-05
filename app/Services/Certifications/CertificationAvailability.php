<?php

namespace App\Services\Certifications;

use App\Models\CertificationAppointment;
use App\Models\CertificationSlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CertificationAvailability
{
    public function __construct(private readonly CertificationConfig $config) {}

    /**
     * @return array<string, mixed>
     */
    public function monthAvailability(string $month): array
    {
        $tz = $this->config->timezone();
        $start = $this->parseMonth($month, $tz);
        $end = $start->endOfMonth();
        $today = CarbonImmutable::now($tz)->startOfDay();

        $days = [];

        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $slots = $date->lessThan($today) ? [] : $this->slotsForDate($date);

            $days[] = [
                'date' => $date->toDateString(),
                'available' => collect($slots)->contains(fn (array $slot) => $slot['available']),
                'slots' => $slots,
            ];
        }

        return [
            'month' => $start->format('Y-m'),
            'timezone' => $tz,
            'schedule_configured' => $this->config->hasConfiguredSchedule(),
            'days' => $days,
        ];
    }

    /**
     * @return array<int, array{time: string, available: bool, remaining: int, reason: string|null}>
     */
    public function slotsForDate(CarbonImmutable $date): array
    {
        $tz = $this->config->timezone();
        $date = $date->setTimezone($tz)->startOfDay();

        if (! $this->isDayOpen($date)) {
            return [];
        }

        $duration = $this->config->durationMinutes();
        $interval = $this->config->slotIntervalMinutes();

        $startTime = CarbonImmutable::parse($date->toDateString().' '.$this->dayHours($date)['start'], $tz);
        $endTime = CarbonImmutable::parse($date->toDateString().' '.$this->dayHours($date)['end'], $tz);

        if ($endTime->lessThanOrEqualTo($startTime)) {
            return [];
        }

        $counts = $this->appointmentCounts($date);
        $capacities = CertificationSlot::query()
            ->whereDate('scheduled_date', $date->toDateString())
            ->pluck('capacity', 'scheduled_time');

        $cutoff = CarbonImmutable::now($tz)->addHours($this->config->minNoticeHours());
        $slots = [];

        for ($slotStart = $startTime; $slotStart->addMinutes($duration)->lessThanOrEqualTo($endTime); $slotStart = $slotStart->addMinutes($interval)) {
            $time = $slotStart->format('H:i');
            $capacity = max(1, (int) ($capacities[$time] ?? $this->config->capacityPerSlot()));
            $booked = (int) ($counts[$time] ?? 0);
            $remaining = max(0, $capacity - $booked);
            $beforeNotice = $slotStart->lessThanOrEqualTo($cutoff);

            $slots[] = [
                'time' => $time,
                'available' => ! $beforeNotice && $remaining > 0,
                'remaining' => $remaining,
                'reason' => $beforeNotice ? 'Fuera del tiempo mínimo de anticipación.' : ($remaining === 0 ? 'Horario ocupado.' : null),
            ];
        }

        return $slots;
    }

    public function isDayOpen(CarbonImmutable $date): bool
    {
        $tz = $this->config->timezone();
        $date = $date->setTimezone($tz)->startOfDay();

        if ($date->lessThan(CarbonImmutable::now($tz)->startOfDay())) {
            return false;
        }

        if (in_array($date->toDateString(), $this->config->closedDates(), true)) {
            return false;
        }

        return (bool) ($this->dayHours($date)['enabled'] ?? false);
    }

    public function slotStartAt(CarbonImmutable $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString().' '.$time, $this->config->timezone());
    }

    /**
     * @return array{enabled: bool, start: string, end: string}
     */
    private function dayHours(CarbonImmutable $date): array
    {
        $hours = $this->config->businessHours();
        $day = $hours[$date->dayOfWeek] ?? null;

        if (! is_array($day)) {
            return ['enabled' => false, 'start' => '09:00', 'end' => '18:00'];
        }

        return [
            'enabled' => (bool) ($day['enabled'] ?? false),
            'start' => (string) ($day['start'] ?? '09:00'),
            'end' => (string) ($day['end'] ?? '18:00'),
        ];
    }

    /**
     * @return Collection<string, int>
     */
    private function appointmentCounts(CarbonImmutable $date): Collection
    {
        return CertificationAppointment::query()
            ->whereDate('scheduled_date', $date->toDateString())
            ->whereIn('status', CertificationAppointment::ACTIVE_STATUSES)
            ->selectRaw('scheduled_time, count(*) as aggregate')
            ->groupBy('scheduled_time')
            ->pluck('aggregate', 'scheduled_time');
    }

    private function parseMonth(string $month, string $tz): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $parsed = CarbonImmutable::createFromFormat('!Y-m', $month, $tz);

            if ($parsed !== false) {
                return $parsed->startOfMonth();
            }
        }

        return CarbonImmutable::now($tz)->startOfMonth();
    }
}
