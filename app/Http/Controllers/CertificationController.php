<?php

namespace App\Http\Controllers;

use App\Models\CertificationAppointment;
use App\Services\Certifications\CertificationAvailability;
use App\Services\Certifications\CertificationBooking;
use App\Services\Certifications\CertificationConfig;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CertificationController extends Controller
{
    public function __construct(
        private readonly CertificationConfig $config,
        private readonly CertificationAvailability $availability,
        private readonly CertificationBooking $booking,
    ) {}

    public function availability(Request $request): JsonResponse
    {
        $month = (string) $request->query('month', '');

        return response()->json([
            ...$this->config->publicPayload(),
            ...$this->availability->monthAvailability($month),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'whatsapp' => ['required', 'string', 'min:10', 'max:20', 'regex:/^[0-9+()\-\s]+$/'],
            'state' => ['required', 'string', 'min:2', 'max:80'],
            'scheduled_date' => ['required', 'date_format:Y-m-d'],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'brand' => ['required', 'string', 'min:1', 'max:80'],
            'model' => ['required', 'string', 'min:1', 'max:80'],
            'year' => ['required', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $timezone = $this->config->timezone();
        $date = CarbonImmutable::parse($validated['scheduled_date'], $timezone)->startOfDay();

        if ($date->lessThan(CarbonImmutable::now($timezone)->startOfDay())) {
            throw ValidationException::withMessages([
                'scheduled_date' => 'Selecciona una fecha a partir de hoy.',
            ]);
        }

        $appointment = $this->booking->book($validated, $request->user());

        return response()->json([
            'appointment' => $this->appointmentPayload($appointment),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function appointmentPayload(CertificationAppointment $appointment): array
    {
        return [
            'folio' => $appointment->folio,
            'full_name' => $appointment->full_name,
            'service' => $appointment->service,
            'scheduled_date' => $appointment->scheduled_date->toDateString(),
            'scheduled_time' => $appointment->scheduled_time,
            'timezone' => $appointment->timezone,
            'duration_minutes' => $appointment->duration_minutes,
            'vehicle' => $appointment->vehicleLabel(),
            'base_price' => (float) $appointment->base_price,
            'tax_amount' => (float) $appointment->tax_amount,
            'total' => (float) $appointment->total,
            'currency' => $appointment->currency,
            'status' => $appointment->status,
            'status_label' => $appointment->statusLabel(),
            'workshop' => $this->config->workshop(),
        ];
    }
}
