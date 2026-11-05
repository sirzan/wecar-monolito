<?php

use App\Mail\CertificationRequestAdmin;
use App\Mail\CertificationRequestReceived;
use App\Models\CertificationAppointment;
use App\Models\SiteSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function configureCertifications(array $overrides = []): void
{
    SiteSetting::put('certification_config', array_replace([
        'business_hours' => collect(range(0, 6))
            ->mapWithKeys(fn (int $day) => [$day => ['enabled' => true, 'start' => '09:00', 'end' => '18:00']])
            ->all(),
        'duration_minutes' => 60,
        'slot_interval_minutes' => 60,
        'capacity_per_slot' => 1,
        'min_notice_hours' => 0,
        'closed_dates' => [],
    ], $overrides));
}

function bookingDate(): string
{
    return CarbonImmutable::now('America/Monterrey')->addDay()->toDateString();
}

function appointmentPayload(array $overrides = []): array
{
    return array_replace([
        'full_name' => 'María López',
        'email' => 'maria@example.com',
        'whatsapp' => '8112345678',
        'state' => 'Nuevo León',
        'scheduled_date' => bookingDate(),
        'scheduled_time' => '10:00',
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'year' => 2022,
        'notes' => 'Prueba automatizada.',
    ], $overrides);
}

it('renders the certifications page with the booking configuration', function () {
    $this->get('/certificaciones')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Certificaciones')
            ->has('brands')
            ->has('certification.pricing')
            ->where('certification.pricing.base_price', 1390)
        );
});

it('returns availability calculated on the server', function () {
    configureCertifications();

    $response = $this->getJson('/api/certificaciones/disponibilidad?month='.substr(bookingDate(), 0, 7))
        ->assertOk()
        ->assertJsonPath('timezone', 'America/Monterrey');

    $days = collect($response->json('days'))->keyBy('date');

    expect($days[bookingDate()]['available'])->toBeTrue()
        ->and($days[bookingDate()]['slots'])->toHaveCount(9);
});

it('registers an appointment with the centralized price and sends notifications', function () {
    Mail::fake();
    configureCertifications();

    $response = $this->postJson('/api/certificaciones/citas', appointmentPayload())
        ->assertCreated();

    $folio = $response->json('appointment.folio');

    expect($folio)->toStartWith('CERT-');

    $appointment = CertificationAppointment::query()->where('folio', $folio)->firstOrFail();

    expect($appointment->status)->toBe(CertificationAppointment::STATUS_PENDING)
        ->and((float) $appointment->base_price)->toBe(1390.0)
        ->and((float) $appointment->tax_amount)->toBe(222.4)
        ->and((float) $appointment->total)->toBe(1612.4)
        ->and($appointment->scheduled_time)->toBe('10:00');

    Mail::assertQueued(CertificationRequestReceived::class);
    Mail::assertQueued(CertificationRequestAdmin::class);
});

it('rejects a second appointment when the slot capacity is full', function () {
    Mail::fake();
    configureCertifications(['capacity_per_slot' => 1]);

    $this->postJson('/api/certificaciones/citas', appointmentPayload())->assertCreated();

    $this->postJson('/api/certificaciones/citas', appointmentPayload(['email' => 'otro@example.com']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['scheduled_time']);

    expect(CertificationAppointment::query()->count())->toBe(1);
});

it('does not offer closed dates', function () {
    configureCertifications(['closed_dates' => [bookingDate()]]);

    $response = $this->getJson('/api/certificaciones/disponibilidad?month='.substr(bookingDate(), 0, 7))
        ->assertOk();

    $days = collect($response->json('days'))->keyBy('date');

    expect($days[bookingDate()]['available'])->toBeFalse();
});

it('validates required fields before creating an appointment', function () {
    configureCertifications();

    $this->postJson('/api/certificaciones/citas', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['full_name', 'email', 'whatsapp', 'state', 'scheduled_date', 'scheduled_time', 'brand', 'model', 'year']);
});

it('lets administrators confirm and cancel appointments', function () {
    $this->seed();
    configureCertifications();

    $admin = User::factory()->create();
    $admin->assignRole('superadmin');

    $appointment = CertificationAppointment::query()->create([
        ...appointmentPayload(),
        'folio' => 'CERT-TEST-00001',
        'timezone' => 'America/Monterrey',
        'starts_at' => CarbonImmutable::now('America/Monterrey')->addDay()->setTime(10, 0)->utc(),
        'duration_minutes' => 60,
        'service' => 'Revisión mecánica y legal',
        'base_price' => 1390,
        'tax_rate' => 0.16,
        'tax_amount' => 222.4,
        'total' => 1612.4,
        'currency' => 'MXN',
        'status' => CertificationAppointment::STATUS_PENDING,
    ]);

    $this->actingAs($admin)
        ->patch("/admin/certificaciones/{$appointment->getKey()}", ['status' => 'confirmed'])
        ->assertRedirect();

    expect($appointment->fresh()->status)->toBe('confirmed');

    $this->actingAs($admin)
        ->patch("/admin/certificaciones/{$appointment->getKey()}", ['status' => 'cancelled'])
        ->assertRedirect();

    $cancelled = $appointment->fresh();

    expect($cancelled->status)->toBe('cancelled')
        ->and($cancelled->cancelled_at)->not->toBeNull();
});

it('forbids viewers from changing appointment status', function () {
    $this->seed();

    $viewer = User::factory()->create();    $viewer->assignRole('viewer');

    $appointment = CertificationAppointment::query()->create([
        ...appointmentPayload(),
        'folio' => 'CERT-TEST-00002',
        'timezone' => 'America/Monterrey',
        'starts_at' => now(),
        'duration_minutes' => 60,
        'service' => 'Revisión mecánica y legal',
        'base_price' => 1390,
        'tax_rate' => 0.16,
        'tax_amount' => 222.4,
        'total' => 1612.4,
        'currency' => 'MXN',
        'status' => CertificationAppointment::STATUS_PENDING,
    ]);

    $this->actingAs($viewer)
        ->patch("/admin/certificaciones/{$appointment->getKey()}", ['status' => 'confirmed'])
        ->assertForbidden();
});

it('shows appointments in the admin panel', function () {
    $this->seed();
    configureCertifications();

    $admin = User::factory()->create();
    $admin->assignRole('superadmin');

    $appointment = CertificationAppointment::query()->create([
        ...appointmentPayload(),
        'folio' => 'CERT-TEST-00003',
        'timezone' => 'America/Monterrey',
        'starts_at' => now(),
        'duration_minutes' => 60,
        'service' => 'Revisión mecánica y legal',
        'base_price' => 1390,
        'tax_rate' => 0.16,
        'tax_amount' => 222.4,
        'total' => 1612.4,
        'currency' => 'MXN',
        'status' => CertificationAppointment::STATUS_PENDING,
    ]);

    $this->actingAs($admin)
        ->get('/admin/certificaciones')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Certifications')
            ->has('appointments.data', 1)
            ->where('appointments.data.0.folio', $appointment->folio)
        );
});

it('reschedules an appointment only into an available slot', function () {
    $this->seed();
    configureCertifications();
    Mail::fake();

    $admin = User::factory()->create();
    $admin->assignRole('superadmin');

    $appointment = CertificationAppointment::query()->create([
        ...appointmentPayload(['scheduled_time' => '10:00']),
        'folio' => 'CERT-TEST-00004',
        'timezone' => 'America/Monterrey',
        'starts_at' => now(),
        'duration_minutes' => 60,
        'service' => 'Revisión mecánica y legal',
        'base_price' => 1390,
        'tax_rate' => 0.16,
        'tax_amount' => 222.4,
        'total' => 1612.4,
        'currency' => 'MXN',
        'status' => CertificationAppointment::STATUS_PENDING,
    ]);

    $this->actingAs($admin)
        ->patch("/admin/certificaciones/{$appointment->getKey()}", [
            'scheduled_date' => bookingDate(),
            'scheduled_time' => '11:00',
        ])
        ->assertRedirect();

    expect($appointment->fresh()->scheduled_time)->toBe('11:00');

    $this->postJson('/api/certificaciones/citas', appointmentPayload(['scheduled_time' => '12:00', 'email' => 'ocupado@example.com']))
        ->assertCreated();

    $this->actingAs($admin)
        ->patch("/admin/certificaciones/{$appointment->getKey()}", [
            'scheduled_date' => bookingDate(),
            'scheduled_time' => '12:00',
        ])
        ->assertSessionHasErrors('scheduled_time');
});
