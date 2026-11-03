<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificationAppointment;
use App\Services\Certifications\CertificationBooking;
use App\Services\Certifications\CertificationConfig;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CertificationController extends Controller
{
    public function __construct(
        private readonly CertificationConfig $config,
        private readonly CertificationBooking $booking,
    ) {}

    public function index(Request $request): Response
    {
        $timezone = $this->config->timezone();
        $today = CarbonImmutable::now($timezone)->toDateString();
        $filters = $request->only(['q', 'status', 'date']);

        $appointments = CertificationAppointment::query()
            ->when($filters['q'] ?? null, function ($query, string $term) {
                $term = '%'.trim($term).'%';
                $query->where(function ($query) use ($term) {
                    $query->whereLike('folio', $term)
                        ->orWhereLike('full_name', $term)
                        ->orWhereLike('whatsapp', $term)
                        ->orWhereLike('email', $term);
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['date'] ?? null, fn ($query, string $date) => $query->whereDate('scheduled_date', $date))
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Certifications', [
            'appointments' => $appointments->through(fn (CertificationAppointment $appointment) => [
                'id' => $appointment->getKey(),
                'folio' => $appointment->folio,
                'full_name' => $appointment->full_name,
                'email' => $appointment->email,
                'whatsapp' => $appointment->whatsapp,
                'state' => $appointment->state,
                'vehicle' => $appointment->vehicleLabel(),
                'brand' => $appointment->brand,
                'model' => $appointment->model,
                'year' => $appointment->year,
                'scheduled_date' => $appointment->scheduled_date->toDateString(),
                'scheduled_time' => $appointment->scheduled_time,
                'status' => $appointment->status,
                'status_label' => $appointment->statusLabel(),
                'notes' => $appointment->notes,
                'total' => (float) $appointment->total,
                'currency' => $appointment->currency,
                'created_at' => $appointment->created_at?->toIso8601String(),
            ]),
            'filters' => $filters,
            'statuses' => CertificationAppointment::STATUS_LABELS,
            'counts' => [
                'today' => CertificationAppointment::query()->whereDate('scheduled_date', $today)->whereIn('status', CertificationAppointment::ACTIVE_STATUSES)->count(),
                'upcoming' => CertificationAppointment::query()->whereDate('scheduled_date', '>', $today)->whereIn('status', CertificationAppointment::ACTIVE_STATUSES)->count(),
                'pending' => CertificationAppointment::query()->where('status', CertificationAppointment::STATUS_PENDING)->count(),
                'confirmed' => CertificationAppointment::query()->where('status', CertificationAppointment::STATUS_CONFIRMED)->count(),
                'completed' => CertificationAppointment::query()->where('status', CertificationAppointment::STATUS_COMPLETED)->count(),
                'cancelled' => CertificationAppointment::query()->where('status', CertificationAppointment::STATUS_CANCELLED)->count(),
            ],
        ]);
    }

    public function update(Request $request, CertificationAppointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(CertificationAppointment::STATUS_LABELS))],
            'scheduled_date' => ['nullable', 'date_format:Y-m-d'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
        ]);

        if (isset($validated['scheduled_date'], $validated['scheduled_time'])) {
            $this->booking->reschedule($appointment, $validated['scheduled_date'], $validated['scheduled_time']);
        }

        if (isset($validated['status'])) {
            $status = $validated['status'];

            $appointment->update([
                'status' => $status,
                'status_changed_at' => now(),
                'cancelled_at' => $status === CertificationAppointment::STATUS_CANCELLED ? now() : $appointment->cancelled_at,
            ]);
        }

        return back()->with('success', 'Cita actualizada correctamente.');
    }
}
