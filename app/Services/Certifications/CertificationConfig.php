<?php

namespace App\Services\Certifications;

use App\Models\SiteSetting;

class CertificationConfig
{
    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $defaults = [
            'business_hours' => config('certifications.business_hours', []),
            'duration_minutes' => (int) config('certifications.duration_minutes', 60),
            'slot_interval_minutes' => (int) config('certifications.slot_interval_minutes', 60),
            'capacity_per_slot' => (int) config('certifications.capacity_per_slot', 1),
            'min_notice_hours' => (int) config('certifications.min_notice_hours', 24),
            'closed_dates' => config('certifications.closed_dates', []),
        ];

        $stored = SiteSetting::get('certification_config', []);

        if (! is_array($stored)) {
            return $defaults;
        }

        return array_replace_recursive($defaults, $stored);
    }

    public function timezone(): string
    {
        return (string) config('certifications.timezone', 'America/Monterrey');
    }

    public function serviceName(): string
    {
        return (string) config('certifications.service', 'Revisión mecánica y legal');
    }

    public function points(): int
    {
        return (int) config('certifications.points', 160);
    }

    public function durationMinutes(): int
    {
        return max(15, (int) ($this->settings()['duration_minutes'] ?? config('certifications.duration_minutes', 60)));
    }

    public function slotIntervalMinutes(): int
    {
        return max(15, (int) ($this->settings()['slot_interval_minutes'] ?? config('certifications.slot_interval_minutes', 60)));
    }

    public function capacityPerSlot(): int
    {
        return max(1, (int) ($this->settings()['capacity_per_slot'] ?? config('certifications.capacity_per_slot', 1)));
    }

    public function minNoticeHours(): int
    {
        return max(0, (int) ($this->settings()['min_notice_hours'] ?? config('certifications.min_notice_hours', 24)));
    }

    /**
     * @return array<int, array{enabled: bool, start: string, end: string}>
     */
    public function businessHours(): array
    {
        $hours = $this->settings()['business_hours'] ?? [];

        return is_array($hours) ? $hours : [];
    }

    /**
     * @return array<int, string>
     */
    public function closedDates(): array
    {
        $dates = $this->settings()['closed_dates'] ?? [];

        if (! is_array($dates)) {
            return [];
        }

        $normalized = [];

        foreach ($dates as $entry) {
            $date = is_array($entry) ? ($entry['date'] ?? null) : $entry;

            if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                $normalized[] = $date;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<string, mixed>
     */
    public function workshop(): array
    {
        return (array) config('certifications.workshop', []);
    }

    public function adminEmail(): string
    {
        return (string) config('certifications.admin_email', 'hola@wecar.mx');
    }

    /**
     * @return array{base_price: float, tax_rate: float, tax_amount: float, total: float, currency: string, label: string}
     */
    public function pricing(): array
    {
        $base = round((float) config('certifications.base_price', 1390), 2);
        $rate = round((float) config('certifications.tax_rate', 0.16), 4);
        $tax = round($base * $rate, 2);
        $currency = (string) config('certifications.currency', 'MXN');

        return [
            'base_price' => $base,
            'tax_rate' => $rate,
            'tax_amount' => $tax,
            'total' => round($base + $tax, 2),
            'currency' => $currency,
            'label' => '$'.number_format($base, 0).' '.$currency.' + IVA',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        return [
            'service' => $this->serviceName(),
            'points' => $this->points(),
            'timezone' => $this->timezone(),
            'duration_minutes' => $this->durationMinutes(),
            'min_notice_hours' => $this->minNoticeHours(),
            'capacity_per_slot' => $this->capacityPerSlot(),
            'pricing' => $this->pricing(),
            'workshop' => $this->workshop(),
            'schedule_configured' => $this->hasConfiguredSchedule(),
        ];
    }

    public function hasConfiguredSchedule(): bool
    {
        foreach ($this->businessHours() as $day) {
            if (is_array($day) && ($day['enabled'] ?? false)) {
                return true;
            }
        }

        return false;
    }
}
