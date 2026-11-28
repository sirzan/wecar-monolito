<?php

use App\Mail\CreditApplicationReceived;
use App\Mail\CreditApplicationSubmitted;
use App\Models\CreditApplication;
use App\Models\Financiera;
use App\Models\Profile;
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

it('requires an active membership for restricted financial offers', function () {
    $financiera = Financiera::query()->create([
        'slug' => 'membership-only',
        'nombre' => 'Financiera con membresía',
        'tipo' => 'fintech',
        'tasa_min' => 12,
        'tasa_max' => 18,
        'cat_min' => 14,
        'cat_max' => 20,
        'enganche_pct' => 10,
        'plazo_max' => 60,
        'score_min' => 500,
        'activo' => true,
        'vehicle_condition' => 'any',
        'requires_membership' => true,
    ]);

    $input = [
        'precio' => 200000,
        'enganche' => 50000,
        'plazo' => 48,
        'car_year' => now()->year - 2,
    ];

    $withoutMembership = collect(app(CreditOfferService::class)->generate($input, 700, collect([$financiera])))
        ->first();
    $withMembership = collect(app(CreditOfferService::class)->generate($input, 700, collect([$financiera]), true))
        ->first();

    expect($withoutMembership['elegible'])->toBeFalse()
        ->and($withoutMembership['motivo'])->toContain('membresía')
        ->and($withMembership['elegible'])->toBeTrue();
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

it('returns saved account configuration in credit profile endpoint and persists updates', function () {
    $user = User::factory()->create([
        'name' => 'Ana López',
        'email' => 'ana.lopez@example.com',
        'phone' => '8112345678',
        'email_verified_at' => null,
    ]);

    Profile::query()->create([
        'user_id' => $user->getKey(),
        'full_name' => 'Ana López',
        'phone' => '8112345678',
        'birthdate' => '1992-08-20',
        'city' => 'Monterrey',
        'state' => 'Nuevo León',
        'rfc' => 'LOAA920820ABC',
    ]);

    $this->actingAs($user)
        ->getJson('/api/credito/perfil')
        ->assertOk()
        ->assertJsonPath('profile.full_name', 'Ana López')
        ->assertJsonPath('profile.phone', '8112345678')
        ->assertJsonPath('profile.alcaldia', 'Monterrey')
        ->assertJsonPath('profile.ciudad', 'Monterrey')
        ->assertJsonPath('profile.state', 'Nuevo León')
        ->assertJsonPath('profile.account_complete', false);

    $this->actingAs($user)
        ->putJson('/api/credito/perfil', [
            'full_name' => 'Ana López García',
            'phone' => '8112345678',
            'birthdate' => '1992-08-20',
            'rfc' => 'loaa920820abc',
            'calle' => 'Av. Constitución 500',
            'colonia' => 'Centro',
            'cp' => '64000',
            'alcaldia' => 'Monterrey',
            'ciudad' => 'Monterrey',
            'state' => 'Nuevo León',
            'pais' => 'México',
            'ocupacion' => 'Arquitecta',
            'empresa' => 'Estudio Norte',
            'antiguedad' => '4 años',
            'antiguedad_anios' => 4,
            'ingreso_mensual' => 45000,
            'deuda_mensual' => 4000,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('profile.account_complete', true)
        ->assertJsonPath('profile.rfc', 'LOAA920820ABC')
        ->assertJsonPath('profile.ocupacion', 'Arquitecta');

    expect($user->fresh()->name)->toBe('Ana López García')
        ->and($user->profile->fresh()->calle)->toBe('Av. Constitución 500')
        ->and($user->profile->fresh()->antiguedad_anios)->toBe(4);
});

it('submits credit application without a vehicle version', function () {
    Storage::fake('local');
    Mail::fake();
    $this->seed();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $financiera = Financiera::query()->where('slug', 'bbva')->firstOrFail();

    $response = $this->actingAs($user)->postJson('/api/credito/solicitudes', [
        'full_name' => 'Carlos Ruiz',
        'email' => 'carlos.ruiz@example.com',
        'phone' => '5511223344',
        'birthdate' => '1988-03-12',
        'calle' => 'Insurgentes Sur 100',
        'colonia' => 'Roma Norte',
        'cp' => '06700',
        'alcaldia' => 'Cuauhtémoc',
        'ciudad' => 'Ciudad de México',
        'state' => 'CDMX',
        'pais' => 'México',
        'ocupacion' => 'Ingeniero',
        'ingreso_mensual' => 50000,
        'deuda_mensual' => 2000,
        'car_brand' => 'Ford',
        'car_model' => 'Fiesta',
        'car_version' => null,
        'car_year' => 2022,
        'car_price' => 260000,
        'car_km' => 30000,
        'financiera_id' => $financiera->getKey(),
        'financiera_name' => 'BBVA Auto',
        'car_condition' => 'used',
        'bureau' => 'bueno',
        'employment' => 'fijo',
        'tenure' => 3,
        'age' => 38,
        'down_payment' => 52000,
        'term_months' => 48,
        'ine' => UploadedFile::fake()->create('ine.pdf', 200, 'application/pdf'),
        'domicilio' => UploadedFile::fake()->create('domicilio.pdf', 200, 'application/pdf'),
        'estado_cuenta' => UploadedFile::fake()->create('estado.pdf', 200, 'application/pdf'),
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    $application = CreditApplication::query()->where('email', 'carlos.ruiz@example.com')->first();
    expect($application)->not->toBeNull()
        ->and($application->car_version)->toBeNull()
        ->and($user->fresh()->profile?->state)->toBe('CDMX');
});

it('redirects back to the credit flow after login and registration while blocking external redirects', function () {
    $this->seed();

    $user = User::factory()->create([
        'email' => 'credito.return@example.com',
        'password' => 'Password123',
        'email_verified_at' => now(),
    ]);

    $this->get('/login?redirect=/credito')
        ->assertOk();

    $this->post('/login', [
        'email' => 'credito.return@example.com',
        'password' => 'Password123',
    ])->assertRedirect('/credito');

    auth()->logout();

    // External URLs must be ignored and fall back to default panel dashboard
    $this->get('/login?redirect=https://evil.example/phish')
        ->assertOk();

    $this->post('/login', [
        'email' => 'credito.return@example.com',
        'password' => 'Password123',
    ])->assertRedirect(route('panel.dashboard'));

    auth()->logout();

    // Registration preserves safe internal redirect
    $this->get('/registro?redirect=/credito')
        ->assertOk();

    $this->post('/register', [
        'account_type' => 'personal',
        'name' => 'Nuevo Cliente',
        'email' => 'nuevo.cliente@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => true,
    ])->assertRedirect('/credito');
});
