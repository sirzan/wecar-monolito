<?php

use App\Mail\CreditApplicationReceived;
use App\Mail\CreditApplicationSubmitted;
use App\Models\CreditApplication;
use App\Models\Financiera;
use App\Models\User;
use App\Services\CreditOfferService;
use App\Services\CreditScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('calculates credit scoring accurately based on buró rules', function () {
    $scoring = app(CreditScoringService::class);

    $result = $scoring->calculate([
        'ingreso' => 50000,
        'edad' => 32,
        'antiguedad' => 4,
        'empleo' => 'fijo',
        'historial' => 'excelente',
        'deudas' => 5000,
        'precio' => 400000,
        'enganche' => 100000,
        'plazo' => 48,
    ]);

    expect($result['score'])->toBeGreaterThanOrEqual(700)
        ->and($result['score'])->toBeLessThanOrEqual(850)
        ->and($result['dti'])->toBeLessThan(0.40)
        ->and($result['monthly_payment'])->toBeGreaterThan(0);
});

it('generates offers and filters banks for vehicles older than 8 years', function () {
    $this->seed();
    $scoring = app(CreditScoringService::class);
    $offersService = app(CreditOfferService::class);

    $oldVehicleOffers = $offersService->generate([
        'precio' => 200000,
        'enganche' => 50000,
        'plazo' => 48,
        'car_year' => now()->year - 10, // 10 years old
    ], 700, Financiera::all());

    $bbvaOffer = collect($oldVehicleOffers)->firstWhere('slug', 'bbva');
    expect($bbvaOffer)->not->toBeNull()
        ->and($bbvaOffer['elegible'])->toBeFalse()
        ->and($bbvaOffer['motivo'])->toContain('8 años');
});

it('returns list of active financieras from api', function () {
    $this->seed();

    $this->getJson('/api/credito/financieras')
        ->assertOk()
        ->assertJsonStructure(['financieras'])
        ->assertJsonCount(39, 'financieras');
});

it('evaluates credit through api with deterministic fallback when no openai key', function () {
    $this->seed();

    $response = $this->postJson('/api/credito/evaluar', [
        'auto' => [
            'marca' => 'Nissan',
            'modelo' => 'Sentra',
            'anio' => 2022,
            'precio' => 320000,
            'km' => 35000,
            'tipo' => 'seminuevo',
        ],
        'perfil' => [
            'ingresoMensual' => 40000,
            'enganche' => 80000,
            'plazoMeses' => 48,
            'historialBuro' => 'excelente',
            'tipoEmpleo' => 'fijo',
            'antiguedadLaboralAnios' => 3,
            'deudasMensuales' => 3000,
            'edad' => 30,
        ],
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'scorePerfilEstimado',
            'nivelRiesgo',
            'capacidadPagoMensualMaxima',
            'dtiCalculadoPct',
            'resumenEjecutivo',
            'recomendaciones',
            'opcionesRecomendadas',
            'opcionesPrendarias',
        ]);

    $data = $response->json();

    expect($data['opcionesRecomendadas'])->not->toBeEmpty()
        ->and($data['opcionesRecomendadas'][0]['mensualidad'])->toBeNumeric()
        ->and($data['opcionesRecomendadas'][0]['mensualidad'])->toBeGreaterThan(0)
        ->and($data['opcionesRecomendadas'][0]['tasa'])->toBeNumeric()
        ->and($data['opcionesRecomendadas'][0]['mensualidadEstimada'])->toBe($data['opcionesRecomendadas'][0]['mensualidad']);
});

it('validates document uploads strictly allowing only pdf up to 5mb', function () {
    Storage::fake('local');
    $user = User::factory()->create(['email_verified_at' => now()]);

    // Reject image
    $this->actingAs($user)
        ->postJson('/api/credito/documentos', [
            'slot' => 'ine',
            'file' => UploadedFile::fake()->image('ine.png'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    // Accept PDF
    $pdf = UploadedFile::fake()->create('ine.pdf', 1024, 'application/pdf');

    $this->actingAs($user)
        ->postJson('/api/credito/documentos', [
            'slot' => 'ine',
            'file' => $pdf,
        ])
        ->assertOk()
        ->assertJson(['success' => true]);
});

it('submits a credit application with files and notifies by email', function () {
    Storage::fake('local');
    Mail::fake();
    $this->seed();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $financiera = Financiera::query()->where('slug', 'bbva')->firstOrFail();

    $payload = [
        'full_name' => 'Juan Pérez Gómez',
        'email' => 'juan.perez@example.com',
        'phone' => '8182838485',
        'birthdate' => '1990-05-15',
        'rfc' => 'PEGJ900515XYZ',
        'curp' => 'PEGJ900515HNLXYZ01',
        'calle' => 'Av. Revolución 1234',
        'colonia' => 'Country',
        'cp' => '64860',
        'alcaldia' => 'Monterrey',
        'ciudad' => 'Monterrey',
        'pais' => 'México',
        'ocupacion' => 'Gerente de Proyectos',
        'empresa' => 'Industrias Monterrey',
        'antiguedad' => '4 años',
        'ingreso_mensual' => 55000,
        'deuda_mensual' => 8000,
        'banco' => 'BBVA',
        'tipo_cuenta' => 'Nómina',
        'car_brand' => 'Mazda',
        'car_model' => 'Mazda 3',
        'car_version' => 'i Grand Touring',
        'car_year' => 2023,
        'car_price' => 380000,
        'car_km' => 25000,
        'financiera_id' => $financiera->getKey(),
        'financiera_name' => 'BBVA Auto',
        'car_condition' => 'used',
        'bureau' => 'excelente',
        'employment' => 'fijo',
        'tenure' => 4,
        'age' => 32,
        'score' => 999,
        'risk_level' => 'bajo',
        'down_payment' => 80000,
        'term_months' => 48,
        'monthly_payment' => 1,
        'rate' => 1,
        'cat' => 1,
        'total_to_pay' => 1,
        'ine' => UploadedFile::fake()->create('ine.pdf', 500, 'application/pdf'),
        'domicilio' => UploadedFile::fake()->create('domicilio.pdf', 500, 'application/pdf'),
        'estado_cuenta' => UploadedFile::fake()->create('ingresos.pdf', 500, 'application/pdf'),
    ];

    $response = $this->actingAs($user)
        ->postJson('/api/credito/solicitudes', $payload);

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'folio',
            'id',
        ]);

    $application = CreditApplication::where('email', 'juan.perez@example.com')->first();
    expect($application)->not->toBeNull()
        ->and($application->status)->toBe('en_revision')
        ->and($application->documents)->toHaveCount(3)
        ->and($application->financiera_id)->toBe($financiera->getKey())
        ->and($application->service_fee)->toBeGreaterThan(0)
        ->and($application->score)->not->toBe(999)
        ->and((float) $application->rate)->toBeGreaterThan(1);

    Mail::assertQueued(CreditApplicationReceived::class);
    Mail::assertQueued(CreditApplicationSubmitted::class);

    // Duplicate prevention within 24h
    $this->actingAs($user)
        ->postJson('/api/credito/solicitudes', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('application');
});

it('allows authorized users and admins to view and manage credit applications', function () {
    $this->seed();
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('superadmin');

    $client = User::factory()->create(['email_verified_at' => now()]);
    $otherClient = User::factory()->create(['email_verified_at' => now()]);

    $app = CreditApplication::factory()->create([
        'user_id' => $client->getKey(),
        'status' => 'en_revision',
    ]);

    // Admin can view credit list
    $this->actingAs($admin)
        ->get('/admin/creditos')
        ->assertOk();

    // Admin can view credit detail
    $this->actingAs($admin)
        ->get("/admin/creditos/{$app->getKey()}")
        ->assertOk();

    // Admin can update status
    $this->actingAs($admin)
        ->patch("/admin/creditos/{$app->getKey()}", [
            'status' => 'aprobado',
            'notes' => 'Aprobado con tasa preferencial.',
        ])
        ->assertRedirect();

    expect($app->fresh()->status)->toBe('aprobado');

    // Panel tramites shows application to owner
    $this->actingAs($client)
        ->get('/panel/tramites')
        ->assertOk();
});
